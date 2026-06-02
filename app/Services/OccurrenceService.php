<?php

namespace App\Services;

use App\Models\Occurrence;
use App\Models\OccurrenceComment;
use App\Models\User;
use App\Notifications\OccurrenceUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class OccurrenceService
{
    /** Adiciona um comentário do usuário e notifica os participantes. */
    public function addComment(Occurrence $occurrence, string $body): OccurrenceComment
    {
        $comment = $this->log($occurrence, 'comment', $body);

        $author = Auth::user()?->name ?? 'Alguém';
        $this->notifyParticipants($occurrence, "{$author} comentou na ocorrência.");

        return $comment;
    }

    /** Altera o status da ocorrência, registra no histórico e notifica. */
    public function changeStatus(Occurrence $occurrence, string $newStatus): Occurrence
    {
        $from = $occurrence->status;
        if ($from === $newStatus) {
            return $occurrence;
        }

        $occurrence->forceFill([
            'status' => $newStatus,
            'closed_at' => $newStatus === 'closed' ? now() : null,
        ])->save();

        $this->log($occurrence, 'status', null, ['from' => $from, 'to' => $newStatus]);

        $label = Occurrence::STATUSES[$newStatus] ?? $newStatus;
        $this->notifyParticipants($occurrence, "Status alterado para \"{$label}\".");

        return $occurrence;
    }

    /** Atribui (ou remove) um responsável, registra e notifica. */
    public function assign(Occurrence $occurrence, ?string $userId): Occurrence
    {
        $occurrence->forceFill(['assigned_to' => $userId])->save();

        $assignee = $userId ? User::find($userId)?->name : null;
        $summary = $assignee ? "Atribuída a {$assignee}." : 'Responsável removido.';
        $this->log($occurrence, 'assignment', null, ['assigned_to' => $userId]);

        $this->notifyParticipants($occurrence, $summary);

        return $occurrence;
    }

    private function log(Occurrence $occurrence, string $type, ?string $body, ?array $meta = null): OccurrenceComment
    {
        return $occurrence->comments()->create([
            'tenant_id' => $occurrence->tenant_id,
            'user_id' => Auth::id(),
            'type' => $type,
            'body' => $body,
            'meta' => $meta,
        ]);
    }

    /**
     * Notifica os participantes da ocorrência (autor e responsável),
     * exceto quem está executando a ação.
     */
    private function notifyParticipants(Occurrence $occurrence, string $summary): void
    {
        $userIds = collect([$occurrence->created_by, $occurrence->assigned_to])
            ->filter()
            ->reject(fn ($id) => $id === Auth::id())
            ->unique();

        if ($userIds->isEmpty()) {
            return;
        }

        $users = User::whereIn('id', $userIds)->where('status', 'active')->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new OccurrenceUpdated($occurrence, $summary));
        }
    }
}
