<?php

namespace App\Notifications;

use App\Models\MaintenancePlan;
use App\Notifications\Concerns\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceDue extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable;

    /** Tenant do job (usado pelo hook de fila p/ aplicar o SMTP do tenant no envio do e-mail). */
    public ?string $tenantId;

    public function __construct(public MaintenancePlan $plan, public int $daysUntilDue)
    {
        $this->tenantId = $plan->tenant_id;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    private function situation(): string
    {
        if ($this->daysUntilDue < 0) {
            return 'está atrasada há '.abs($this->daysUntilDue).' dia(s)';
        }
        if ($this->daysUntilDue === 0) {
            return 'vence hoje';
        }

        return 'vence em '.$this->daysUntilDue.' dia(s)';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->plan->next_due_date?->format('d/m/Y');

        return (new MailMessage)
            ->subject('Manutenção '.$this->situation().' — '.$this->plan->title)
            ->greeting('Olá!')
            ->line("A manutenção \"{$this->plan->title}\" {$this->situation()} (prevista para {$date}).")
            ->action('Ver manutenções', route('maintenance.index'))
            ->line('Agende com o fornecedor para manter o condomínio em conformidade.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Manutenção '.$this->situation(),
            'message' => $this->plan->title.' (prevista para '.$this->plan->next_due_date?->format('d/m/Y').')',
            'url' => route('maintenance.index'),
            'icon' => 'wrench',
        ];
    }
}
