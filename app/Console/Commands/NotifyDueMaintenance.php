<?php

namespace App\Console\Commands;

use App\Models\MaintenancePlan;
use App\Models\User;
use App\Notifications\MaintenanceDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyDueMaintenance extends Command
{
    protected $signature = 'maintenance:notify-due';

    protected $description = 'Notifica os gestores sobre manutenções preventivas próximas do vencimento ou atrasadas.';

    public function handle(): int
    {
        // Sem contexto de tenant: o global scope BelongsToTenant não filtra,
        // varrendo todos os tenants (comportamento desejado para o scheduler).
        $plans = MaintenancePlan::query()
            ->dueForAlert()
            ->with('condominium:id,name')
            ->get();

        $notified = 0;
        $panelUsersByTenant = [];

        foreach ($plans as $plan) {
            $users = $panelUsersByTenant[$plan->tenant_id] ??= User::query()
                ->where('tenant_id', $plan->tenant_id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', User::PANEL_ROLES))
                ->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new MaintenanceDue($plan, $plan->days_until_due));
                $notified++;
            }

            $plan->forceFill(['last_notified_at' => now()])->saveQuietly();
        }

        $this->info("{$plans->count()} manutenção(ões) processada(s); {$notified} com gestores notificados.");

        return self::SUCCESS;
    }
}
