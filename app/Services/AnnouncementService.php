<?php

namespace App\Services;

use App\Mail\AnnouncementPublishedMail;
use App\Models\Announcement;
use App\Models\Person;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class AnnouncementService
{
    public function __construct(private readonly WebhookService $webhooks) {}

    /**
     * Marca o comunicado como publicado e enfileira o e-mail aos moradores
     * com endereço cadastrado no condomínio-alvo. Idempotente: não republica
     * nem reenvia e-mail se já estiver publicado.
     */
    public function publish(Announcement $announcement): Announcement
    {
        if ($announcement->status === 'published' && $announcement->published_at !== null) {
            return $announcement;
        }

        $announcement->forceFill([
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $this->notifyResidents($announcement);
        $this->notifyPanelUsers($announcement);

        $this->webhooks->dispatch($announcement->tenant_id, 'announcement.published', [
            'id' => $announcement->id,
            'condominium_id' => $announcement->condominium_id,
            'title' => $announcement->title,
            'category' => $announcement->category,
            'urgency' => $announcement->urgency,
            'published_at' => $announcement->published_at?->toIso8601String(),
        ]);

        return $announcement;
    }

    /**
     * Notificação in-app aos usuários do painel do tenant (admin/síndico/conselho).
     * Filtra explicitamente por tenant_id — exclui super admins (tenant_id null),
     * o que é necessário no contexto do comando agendado (sem tenant resolvido).
     */
    protected function notifyPanelUsers(Announcement $announcement): void
    {
        $users = User::query()
            ->where('tenant_id', $announcement->tenant_id)
            ->where('status', 'active')
            ->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new AnnouncementPublished($announcement));
        }
    }

    /**
     * Enfileira um e-mail para cada morador (Person com e-mail) vinculado
     * ativamente a uma unidade do condomínio do comunicado.
     */
    protected function notifyResidents(Announcement $announcement): void
    {
        $emails = Person::query()
            ->where('tenant_id', $announcement->tenant_id)
            ->whereNotNull('email')
            ->whereHas('activeLinks.unit', fn ($q) => $q->where('condominium_id', $announcement->condominium_id))
            ->pluck('email')
            ->unique();

        foreach ($emails as $email) {
            Mail::to($email)->queue(new AnnouncementPublishedMail($announcement));
        }
    }
}
