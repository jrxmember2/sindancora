<?php

namespace App\Notifications;

use App\Models\EmployeeVacationPeriod;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeVacationDue extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public ?string $tenantId;

    public function __construct(public EmployeeVacationPeriod $period, public int $daysUntilDeadline)
    {
        $this->tenantId = $period->tenant_id;
        $this->period->loadMissing('employee.condominium');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'employee_vacation_due', ['database', 'mail', 'broadcast']);
    }

    private function situation(): string
    {
        if ($this->daysUntilDeadline < 0) {
            return 'esta atrasada ha '.abs($this->daysUntilDeadline).' dia(s)';
        }

        if ($this->daysUntilDeadline === 0) {
            return 'vence hoje';
        }

        return 'vence em '.$this->daysUntilDeadline.' dia(s)';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->period->employee;
        $deadline = $this->period->deadline_date?->format('d/m/Y');

        return (new MailMessage)
            ->subject('Ferias '.$this->situation().' - '.$employee?->name)
            ->greeting('Ola!')
            ->line("O prazo de ferias de {$employee?->name} {$this->situation()} (limite {$deadline}).")
            ->line('Condominio: '.($employee?->condominium?->name ?? '-').'.')
            ->action('Ver funcionario', route('employees.show', $employee))
            ->line('Revise a programacao para manter o controle trabalhista atualizado.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $employee = $this->period->employee;

        return [
            'title' => 'Ferias '.$this->situation(),
            'message' => ($employee?->name ?? 'Funcionario').' - limite '.$this->period->deadline_date?->format('d/m/Y'),
            'url' => $employee ? route('employees.show', $employee) : route('employees.index'),
            'icon' => 'briefcase',
        ];
    }
}
