<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    /**
     * Registra a execução de uma manutenção, grava no histórico e avança a próxima data prevista
     * conforme a recorrência (recorrência "once" mantém a data atual). Reabre o ciclo de alerta.
     *
     * @param  array{done_date:string, supplier_id?:?string, cost?:?string, notes?:?string, generate_expense?:bool, expense_due_date?:?string, expense_document_number?:?string, expense_reminder_days?:?int}  $data
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

            if (($data['generate_expense'] ?? false) && $record->cost !== null && (float) $record->cost > 0) {
                $this->createExpenseForRecord($plan, $record, $data, $doneDate, $user);
            }

            return $record;
        });
    }

    private function createExpenseForRecord(
        MaintenancePlan $plan,
        MaintenanceRecord $record,
        array $data,
        Carbon $doneDate,
        ?User $user,
    ): Expense {
        $supplierName = $record->supplier_id
            ? Supplier::where('tenant_id', $plan->tenant_id)->whereKey($record->supplier_id)->value('name')
            : null;

        $notes = trim(implode("\n\n", array_filter([
            "Gerada pela execucao da manutencao: {$plan->title}.",
            $data['notes'] ?? null,
        ])));

        return Expense::create([
            'tenant_id' => $plan->tenant_id,
            'condominium_id' => $plan->condominium_id,
            'category' => 'maintenance',
            'description' => "Manutencao: {$plan->title}",
            'amount' => $record->cost,
            'status' => 'pending',
            'expense_date' => $doneDate,
            'due_date' => Carbon::parse($data['expense_due_date'] ?? $doneDate),
            'supplier' => $supplierName,
            'supplier_id' => $record->supplier_id,
            'document_number' => $data['expense_document_number'] ?? null,
            'reminder_days' => $data['expense_reminder_days'] ?? 3,
            'maintenance_record_id' => $record->id,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $user?->id,
        ]);
    }
}
