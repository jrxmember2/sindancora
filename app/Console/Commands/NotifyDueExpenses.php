<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyDueExpenses extends Command
{
    protected $signature = 'expenses:notify-due';

    protected $description = 'Notifica gestores sobre contas a pagar proximas do vencimento ou vencidas.';

    public function handle(): int
    {
        $expenses = Expense::query()
            ->dueForReminder()
            ->with(['condominium:id,name', 'supplierRecord:id,name'])
            ->get()
            ->filter(fn (Expense $expense) => $expense->days_until_due !== null
                && $expense->days_until_due <= $expense->reminder_days);

        $notified = 0;
        $panelUsersByTenant = [];

        foreach ($expenses as $expense) {
            $users = $panelUsersByTenant[$expense->tenant_id] ??= User::query()
                ->where('tenant_id', $expense->tenant_id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', User::PANEL_ROLES))
                ->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new ExpenseDue($expense, $expense->days_until_due));
                $notified++;
            }

            $expense->forceFill(['reminder_sent_at' => now()])->saveQuietly();
        }

        $this->info("{$expenses->count()} conta(s) processada(s); {$notified} com gestores notificados.");

        return self::SUCCESS;
    }
}
