<?php

namespace App\Services;

use App\Models\Occurrence;
use App\Models\OccurrenceComment;
use App\Models\OccurrenceSlaSetting;
use App\Models\User;
use App\Notifications\OccurrenceUpdated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class OccurrenceService
{
    /** @var array<string, OccurrenceSlaSetting|null> cache de SLA por tenant na requisição */
    private array $slaCache = [];

    public function __construct(private readonly WebhookService $webhooks) {}

    /** Dias de SLA para a prioridade, considerando o override do tenant. */
    public function slaDaysFor(string $tenantId, string $priority): int
    {
        $setting = $this->slaCache[$tenantId] ??= OccurrenceSlaSetting::where('tenant_id', $tenantId)->first();

        return $setting
            ? $setting->daysFor($priority)
            : (Occurrence::SLA_DEFAULT_DAYS[$priority] ?? 5);
    }

    /** Define o prazo (due_at) pelo SLA da prioridade, se ainda não houver um. */
    public function ensureDueAt(Occurrence $occurrence): Occurrence
    {
        if ($occurrence->due_at) {
            return $occurrence;
        }

        $base = $occurrence->created_at ?? now();
        $days = $this->slaDaysFor($occurrence->tenant_id, $occurrence->priority);

        $occurrence->forceFill(['due_at' => $base->copy()->addDays($days)])->save();

        return $occurrence;
    }

    /** Adiciona um comentário do usuário e notifica os participantes. */
    public function addComment(Occurrence $occurrence, string $body, bool $isInternal = false): OccurrenceComment
    {
        $comment = $this->log($occurrence, 'comment', $body, null, $isInternal);

        $this->markFirstResponse($occurrence);

        $author = Auth::user()?->name ?? 'Alguém';
        // Nota interna não avisa o morador (autor da ocorrência) — só o responsável.
        $this->notifyParticipants($occurrence, "{$author} comentou na ocorrência.", excludeCreator: $isInternal);

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
            // Ao reabrir, reabre o ciclo de alerta de SLA.
            'sla_notified_at' => $newStatus !== 'closed' ? null : $occurrence->sla_notified_at,
        ])->save();

        // Sair de "aberta" conta como primeira resposta da gestão.
        if ($from === 'open') {
            $this->markFirstResponse($occurrence);
        }

        $this->log($occurrence, 'status', null, ['from' => $from, 'to' => $newStatus]);

        $label = Occurrence::STATUSES[$newStatus] ?? $newStatus;
        $this->notifyParticipants($occurrence, "Status alterado para \"{$label}\".");

        $this->webhooks->dispatch($occurrence->tenant_id, 'occurrence.status_changed', [
            'from' => $from,
            'to' => $newStatus,
        ] + $occurrence->toWebhookArray());

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

    /**
     * Notifica os gestores (papéis de painel) do tenant sobre uma ocorrência recém-aberta.
     * Usado quando o morador abre uma ocorrência pelo portal.
     */
    public function notifyNew(Occurrence $occurrence): void
    {
        $managers = User::where('tenant_id', $occurrence->tenant_id)
            ->where('status', 'active')
            ->when(Auth::id(), fn ($q) => $q->where('id', '!=', Auth::id()))
            ->whereHas('userRoles.role', fn ($q) => $q->whereIn('name', User::PANEL_ROLES))
            ->get();

        if ($managers->isNotEmpty()) {
            Notification::send($managers, new OccurrenceUpdated($occurrence, 'Nova ocorrência aberta no portal.'));
        }
    }

    /** Marca a primeira resposta da gestão (ator diferente de quem abriu), para estatística. */
    private function markFirstResponse(Occurrence $occurrence): void
    {
        if ($occurrence->first_response_at || Auth::id() === $occurrence->created_by) {
            return;
        }

        $occurrence->forceFill(['first_response_at' => now()])->save();
    }

    private function log(Occurrence $occurrence, string $type, ?string $body, ?array $meta = null, bool $isInternal = false): OccurrenceComment
    {
        return $occurrence->comments()->create([
            'tenant_id' => $occurrence->tenant_id,
            'user_id' => Auth::id(),
            'type' => $type,
            'body' => $body,
            'is_internal' => $isInternal,
            'meta' => $meta,
        ]);
    }

    /**
     * Notifica os participantes da ocorrência (autor e responsável),
     * exceto quem está executando a ação. `excludeCreator` omite o autor (notas internas).
     */
    private function notifyParticipants(Occurrence $occurrence, string $summary, bool $excludeCreator = false): void
    {
        $userIds = collect([$excludeCreator ? null : $occurrence->created_by, $occurrence->assigned_to])
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
