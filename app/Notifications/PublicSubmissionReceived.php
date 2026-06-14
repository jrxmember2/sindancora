<?php

namespace App\Notifications;

use App\Models\PublicSubmission;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublicSubmissionReceived extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public ?string $tenantId;

    public function __construct(public PublicSubmission $submission)
    {
        $this->tenantId = $submission->tenant_id;
        $this->submission->loadMissing('condominium:id,name');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'public_submission_received', ['database', 'broadcast', FcmChannel::class]);
    }

    /** @return array<string, string> */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Novo envio público — '.$this->typeLabel(),
            'body' => ($this->submission->name ?? 'Solicitante').' • '.($this->submission->condominium?->name ?? '—'),
            'type' => 'public_submission',
        ];
    }

    private function typeLabel(): string
    {
        return PublicSubmission::TYPES[$this->submission->type] ?? 'Envio público';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo envio público para moderar — '.$this->typeLabel())
            ->greeting('Olá!')
            ->line("Há um novo envio público aguardando moderação: {$this->typeLabel()}.")
            ->line('Condomínio: '.($this->submission->condominium?->name ?? '—').'.')
            ->line('Solicitante: '.($this->submission->name ?? '—').'.')
            ->action('Abrir fila de moderação', route('public-links.moderation.show', $this->submission))
            ->line('Aprove ou reprove para concluir o atendimento.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Novo envio público — '.$this->typeLabel(),
            'message' => ($this->submission->name ?? 'Solicitante').' • '.($this->submission->condominium?->name ?? '—'),
            'url' => route('public-links.moderation.show', $this->submission),
            'icon' => 'inbox',
        ];
    }
}
