<?php

namespace App\Console\Commands;

use App\Models\EmployeeVacationPeriod;
use App\Models\User;
use App\Notifications\EmployeeVacationDue;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyDueEmployeeVacations extends Command
{
    protected $signature = 'employees:notify-vacations';

    protected $description = 'Notifica gestores sobre ferias de funcionarios proximas do prazo limite ou atrasadas.';

    public function handle(): int
    {
        $periods = EmployeeVacationPeriod::query()
            ->dueForAlert()
            ->with(['employee:id,tenant_id,condominium_id,name,position,status,vacation_alert_days', 'employee.condominium:id,name'])
            ->get();

        $notified = 0;
        $usersByTenantAndCondominium = [];

        foreach ($periods as $period) {
            $users = $this->usersForPeriod($period, $usersByTenantAndCondominium);

            if ($users->isNotEmpty()) {
                Notification::send($users, new EmployeeVacationDue($period, $period->days_until_deadline ?? 0));
                $notified++;
            }

            $period->forceFill(['notified_at' => now()])->saveQuietly();
        }

        $this->info("{$periods->count()} periodo(s) de ferias processado(s); {$notified} com gestores notificados.");

        return self::SUCCESS;
    }

    /** @param array<string, Collection<int, User>> $cache */
    private function usersForPeriod(EmployeeVacationPeriod $period, array &$cache): Collection
    {
        $condominiumId = $period->employee?->condominium_id;
        $cacheKey = $period->tenant_id.'|'.$condominiumId;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        return $cache[$cacheKey] = User::query()
            ->where('tenant_id', $period->tenant_id)
            ->where('status', 'active')
            ->whereHas('userRoles.role.permissions', fn ($permission) => $permission->where('permissions.name', 'employees:read'))
            ->where(function ($query) use ($condominiumId) {
                $query->whereHas('userRoles', fn ($role) => $role->whereNull('condominium_id'));

                if ($condominiumId) {
                    $query->orWhereHas('userRoles', fn ($role) => $role->where('condominium_id', $condominiumId));
                }
            })
            ->get();
    }
}
