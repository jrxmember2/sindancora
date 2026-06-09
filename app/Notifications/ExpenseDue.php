<?php

namespace App\Notifications;

use App\Models\Expense;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpenseDue extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public ?string $tenantId;

    public function __construct(public Expense $expense, public int $daysUntilDue)
    {
        $this->tenantId = $expense->tenant_id;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'expense_due', ['database', 'mail', 'broadcast']);
    }

    private function situation(): string
    {
        if ($this->daysUntilDue < 0) {
            return 'venceu ha '.abs($this->daysUntilDue).' dia(s)';
        }

        if ($this->daysUntilDue === 0) {
            return 'vence hoje';
        }

        return 'vence em '.$this->daysUntilDue.' dia(s)';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $value = number_format((float) $this->expense->amount, 2, ',', '.');
        $due = $this->expense->due_date?->format('d/m/Y');

        return (new MailMessage)
            ->subject('Conta a pagar '.$this->situation().' - '.$this->expense->description)
            ->greeting('Ola!')
            ->line("A conta \"{$this->expense->description}\" {$this->situation()} (vencimento {$due}).")
            ->line("Valor: R$ {$value}.")
            ->action('Ver contas a pagar', route('expenses.index'))
            ->line('Programe a baixa para manter o fluxo financeiro atualizado.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Conta a pagar '.$this->situation(),
            'message' => $this->expense->description.' - vencimento '.$this->expense->due_date?->format('d/m/Y'),
            'url' => route('expenses.index'),
            'icon' => 'receipt',
        ];
    }
}
