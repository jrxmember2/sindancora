<?php

namespace App\Notifications;

use App\Models\Occurrence;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OccurrenceSlaDue extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    /** Tenant do job (usado pelo hook de fila p/ aplicar o SMTP do tenant no envio do e-mail). */
    public ?string $tenantId;

    public function __construct(public Occurrence $occurrence)
    {
        $this->tenantId = $occurrence->tenant_id;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'occurrence_sla_due', ['database', 'mail', 'broadcast', FcmChannel::class]);
    }

    /** @return array<string, string> */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Chamado '.$this->situation(),
            'body' => $this->occurrence->title,
            'route' => 'occurrences/'.$this->occurrence->id,
            'type' => 'occurrence',
        ];
    }

    private function situation(): string
    {
        $hours = (int) round(now()->diffInHours($this->occurrence->due_at, false));

        if ($hours < 0) {
            return 'está atrasado';
        }

        return 'vence em breve';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->occurrence->due_at?->format('d/m/Y H:i');

        return (new MailMessage)
            ->subject('Chamado '.$this->situation().' — '.$this->occurrence->title)
            ->greeting('Olá!')
            ->line("O chamado \"{$this->occurrence->title}\" {$this->situation()} (prazo {$date}).")
            ->action('Ver chamado', route('occurrences.show', $this->occurrence->id))
            ->line('Atenda dentro do prazo para manter o SLA.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Chamado '.$this->situation(),
            'message' => $this->occurrence->title.' (prazo '.$this->occurrence->due_at?->format('d/m/Y H:i').')',
            'url' => route('occurrences.show', $this->occurrence->id),
            'icon' => 'alert-triangle',
        ];
    }
}
