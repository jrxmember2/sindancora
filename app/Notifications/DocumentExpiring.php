<?php

namespace App\Notifications;

use App\Models\Document;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiring extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    /** Tenant do job (usado pelo hook de fila p/ aplicar o SMTP do tenant no envio do e-mail). */
    public ?string $tenantId;

    public function __construct(public Document $document, public int $daysUntilExpiry)
    {
        $this->tenantId = $document->tenant_id;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'document_expiring', ['database', 'mail', 'broadcast']);
    }

    private function situation(): string
    {
        if ($this->daysUntilExpiry < 0) {
            return 'venceu há '.abs($this->daysUntilExpiry).' dia(s)';
        }
        if ($this->daysUntilExpiry === 0) {
            return 'vence hoje';
        }

        return 'vence em '.$this->daysUntilExpiry.' dia(s)';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->document->valid_until?->format('d/m/Y');

        return (new MailMessage)
            ->subject('Documento '.$this->situation().' — '.$this->document->title)
            ->greeting('Olá!')
            ->line("O documento \"{$this->document->title}\" {$this->situation()} (validade {$date}).")
            ->action('Ver documentos', route('documents.index'))
            ->line('Providencie a renovação para manter o condomínio em conformidade.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Documento '.$this->situation(),
            'message' => $this->document->title.' (validade '.$this->document->valid_until?->format('d/m/Y').')',
            'url' => route('documents.index'),
            'icon' => 'file-text',
        ];
    }
}
