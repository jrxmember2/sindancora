<?php

namespace App\Services;

use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    /**
     * Registra a execução de uma manutenção, grava no histórico e avança a próxima data prevista
     * conforme a recorrência (recorrência "once" mantém a data atual). Reabre o ciclo de alerta.
     *
     * @param  array{done_date:string, supplier_id?:?string, cost?:?string, notes?:?string}  $data
     */
    public function registerExecution(MaintenancePlan $plan, array $data, ?User $user): MaintenanceRecord
    {
        return DB::transaction(function () use ($plan, $data, $user) {
            $doneDate = Carbon::parse($data['done_date']);

            $record = $plan->records()->create([
                'tenant_id' => $plan->tenant_id,
                'supplier_id' => $data['supplier_id'] ?? $plan->supplier_id,
                'user_id' => $user?->id,
                'done_date' => $doneDate,
                'cost' => $data['cost'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $next = $plan->nextDateFrom($doneDate);

            $plan->forceFill([
                'last_done_date' => $doneDate,
                'next_due_date' => $next ?? $plan->next_due_date,
                'last_notified_at' => null,
            ])->save();

            return $record;
        });
    }
}
