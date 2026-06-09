<?php

namespace App\Services\Reports;

use App\Models\Charge;
use App\Models\Condominium;
use App\Models\Document;
use App\Models\Expense;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\Occurrence;
use App\Models\PersonUnitLink;
use App\Models\Quotation;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ConsolidatedReportBuilder
{
    private const MODULES = [
        'financial' => [
            'label' => 'Financeiro',
            'plan_module' => 'financial',
            'permissions' => ['charges:read', 'expenses:read'],
        ],
        'occurrences' => [
            'label' => 'Ocorrencias',
            'plan_module' => 'occurrences',
            'permissions' => ['occurrences:read'],
        ],
        'reservations' => [
            'label' => 'Reservas',
            'plan_module' => 'reservations',
            'permissions' => ['reservations:read'],
        ],
        'maintenance' => [
            'label' => 'Manutencoes',
            'plan_module' => 'maintenance',
            'permissions' => ['maintenance:read'],
        ],
        'works' => [
            'label' => 'Obras/Reformas',
            'plan_module' => 'works',
            'permissions' => ['works:read'],
        ],
        'documents' => [
            'label' => 'Documentos',
            'plan_module' => 'documents',
            'permissions' => ['documents:read'],
        ],
        'quotations' => [
            'label' => 'Orcamentos',
            'plan_module' => 'quotations',
            'permissions' => ['quotations:read'],
        ],
    ];

    /**
     * @param array<int,string> $selectedCondominiumIds
     * @param array<int,string> $selectedModules
     */
    public function build(Tenant $tenant, User $user, Carbon $from, Carbon $to, array $selectedCondominiumIds = [], array $selectedModules = []): array
    {
        $availableCondominiums = $this->accessibleCondominiums($tenant, $user);
        $availableIds = $availableCondominiums->pluck('id')->all();
        $selectedCondominiumIds = array_values(array_intersect($selectedCondominiumIds, $availableIds));
        $scopeIds = $selectedCondominiumIds !== [] ? $selectedCondominiumIds : $availableIds;

        $availableModules = $this->availableModules($tenant, $user);
        $availableModuleIds = array_keys($availableModules);
        $selectedModules = array_values(array_intersect($selectedModules, $availableModuleIds));
        $activeModules = $selectedModules !== [] ? $selectedModules : $availableModuleIds;

        $rows = $this->baseRows($tenant, $availableCondominiums, $scopeIds);

        if (in_array('financial', $activeModules, true)) {
            $this->applyFinancial($rows, $tenant, $scopeIds, $from, $to);
        }

        if (in_array('occurrences', $activeModules, true)) {
            $this->applyOccurrences($rows, $tenant, $scopeIds, $from, $to);
        }

        if (in_array('reservations', $activeModules, true)) {
            $this->applyReservations($rows, $tenant, $scopeIds, $from, $to);
        }

        if (in_array('maintenance', $activeModules, true)) {
            $this->applyMaintenance($rows, $tenant, $scopeIds, $from, $to);
        }

        if (in_array('works', $activeModules, true)) {
            $this->applyWorks($rows, $tenant, $scopeIds, $from, $to);
        }

        if (in_array('documents', $activeModules, true)) {
            $this->applyDocuments($rows, $tenant, $scopeIds, $from, $to);
        }

        if (in_array('quotations', $activeModules, true)) {
            $this->applyQuotations($rows, $tenant, $scopeIds, $from, $to);
        }

        $rows = $rows
            ->filter(fn (array $row) => in_array($row['condominium']['id'], $scopeIds, true))
            ->values()
            ->map(fn (array $row) => $this->finalizeRow($row));

        return [
            'summary' => $this->summary($rows, $activeModules),
            'by_condominium' => $rows,
            'monthly' => $this->monthly($tenant, $scopeIds, $activeModules, $from, $to),
            'rankings' => $this->rankings($rows),
            'available_modules' => collect($availableModules)
                ->map(fn (array $module, string $value) => ['value' => $value, 'label' => $module['label']])
                ->values(),
            'available_condominiums' => $availableCondominiums
                ->map(fn (Condominium $condominium) => ['value' => $condominium->id, 'label' => $condominium->name])
                ->values(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'condominium_ids' => $selectedCondominiumIds,
                'modules' => $selectedModules,
            ],
        ];
    }

    private function accessibleCondominiums(Tenant $tenant, User $user): Collection
    {
        $query = Condominium::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('name');

        if (! $user->isSuperAdmin() && ! $this->hasTenantWideCondominiumAccess($user)) {
            $ids = $user->userRoles()
                ->whereNotNull('condominium_id')
                ->pluck('condominium_id')
                ->unique()
                ->values()
                ->all();

            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'name', 'city', 'state', 'status']);
    }

    private function hasTenantWideCondominiumAccess(User $user): bool
    {
        return $user->isSuperAdmin() || $user->userRoles()->whereNull('condominium_id')->exists();
    }

    private function availableModules(Tenant $tenant, User $user): array
    {
        return collect(self::MODULES)
            ->filter(fn (array $module) => $this->canUseModule($tenant, $user, $module))
            ->all();
    }

    private function canUseModule(Tenant $tenant, User $user, array $module): bool
    {
        if (! $user->isSuperAdmin() && ! $tenant->activePlan()?->hasModule($module['plan_module'])) {
            return false;
        }

        return $user->isSuperAdmin()
            || collect($module['permissions'])->contains(fn (string $permission) => $user->hasPermission($permission));
    }

    private function baseRows(Tenant $tenant, Collection $condominiums, array $scopeIds): Collection
    {
        $rows = $condominiums->mapWithKeys(fn (Condominium $condominium) => [$condominium->id => [
            'condominium' => [
                'id' => $condominium->id,
                'name' => $condominium->name,
                'city' => $condominium->city,
                'state' => $condominium->state,
            ],
            'structure' => [
                'units' => 0,
                'occupied_units' => 0,
                'vacant_units' => 0,
                'renovation_units' => 0,
                'residents' => 0,
            ],
            'financial' => $this->financialDefaults(),
            'occurrences' => $this->occurrenceDefaults(),
            'reservations' => $this->reservationDefaults(),
            'maintenance' => $this->maintenanceDefaults(),
            'works' => $this->workDefaults(),
            'documents' => $this->documentDefaults(),
            'quotations' => $this->quotationDefaults(),
            'risk' => ['score' => 0, 'level' => 'Baixo'],
        ]]);

        if ($scopeIds === []) {
            return $rows;
        }

        $units = Unit::where('tenant_id', $tenant->id)
            ->whereIn('condominium_id', $scopeIds)
            ->selectRaw('condominium_id, count(*) as total')
            ->selectRaw("sum(case when status = 'occupied' then 1 else 0 end) as occupied")
            ->selectRaw("sum(case when status = 'vacant' then 1 else 0 end) as vacant")
            ->selectRaw("sum(case when status = 'under_renovation' then 1 else 0 end) as renovation")
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $residents = PersonUnitLink::query()
            ->join('units', 'units.id', '=', 'person_unit_links.unit_id')
            ->where('person_unit_links.tenant_id', $tenant->id)
            ->whereNull('person_unit_links.end_date')
            ->whereIn('units.condominium_id', $scopeIds)
            ->selectRaw('units.condominium_id as condominium_id, count(distinct person_unit_links.person_id) as total')
            ->groupBy('units.condominium_id')
            ->get()
            ->keyBy('condominium_id');

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $unit = $units[$id] ?? null;
            $resident = $residents[$id] ?? null;

            $row['structure'] = [
                'units' => (int) ($unit->total ?? 0),
                'occupied_units' => (int) ($unit->occupied ?? 0),
                'vacant_units' => (int) ($unit->vacant ?? 0),
                'renovation_units' => (int) ($unit->renovation ?? 0),
                'residents' => (int) ($resident->total ?? 0),
            ];

            $rows[$id] = $row;
        }

        return $rows;
    }

    private function applyFinancial(Collection &$rows, Tenant $tenant, array $scopeIds, Carbon $from, Carbon $to): void
    {
        if ($scopeIds === []) {
            return;
        }

        $charged = $this->chargeBase($tenant, $scopeIds)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('condominium_id, count(*) as count, coalesce(sum(amount), 0) as total')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $received = $this->chargeBase($tenant, $scopeIds)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('condominium_id, coalesce(sum(paid_amount), 0) as total')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $open = $this->chargeBase($tenant, $scopeIds)
            ->open()
            ->selectRaw('condominium_id, count(*) as count, coalesce(sum(amount), 0) as total')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $expensesPaid = $this->expenseBase($tenant, $scopeIds)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('condominium_id, coalesce(sum(paid_amount), 0) as total')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $expensesOpen = $this->expenseBase($tenant, $scopeIds)
            ->open()
            ->selectRaw('condominium_id, count(*) as count, coalesce(sum(amount), 0) as total')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $overdueByCondo = $this->chargeBase($tenant, $scopeIds)
            ->overdue()
            ->get(['id', 'condominium_id', 'unit_id', 'amount', 'fine_rate', 'interest_rate', 'due_date', 'status'])
            ->groupBy('condominium_id')
            ->map(fn (Collection $charges) => [
                'total' => round($charges->sum(fn (Charge $charge) => $charge->currentAmount()), 2),
                'units' => $charges->pluck('unit_id')->filter()->unique()->count(),
            ]);

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $chargedRow = $charged[$id] ?? null;
            $receivedRow = $received[$id] ?? null;
            $openRow = $open[$id] ?? null;
            $expensePaidRow = $expensesPaid[$id] ?? null;
            $expenseOpenRow = $expensesOpen[$id] ?? null;
            $overdue = $overdueByCondo[$id] ?? ['total' => 0, 'units' => 0];
            $receivedTotal = (float) ($receivedRow->total ?? 0);
            $expensePaidTotal = (float) ($expensePaidRow->total ?? 0);

            $row['financial'] = [
                'charged' => (float) ($chargedRow->total ?? 0),
                'charged_count' => (int) ($chargedRow->count ?? 0),
                'received' => $receivedTotal,
                'expenses_paid' => $expensePaidTotal,
                'balance' => round($receivedTotal - $expensePaidTotal, 2),
                'open_charges' => (float) ($openRow->total ?? 0),
                'open_charges_count' => (int) ($openRow->count ?? 0),
                'overdue_charges' => (float) $overdue['total'],
                'delinquent_units' => (int) $overdue['units'],
                'open_expenses' => (float) ($expenseOpenRow->total ?? 0),
                'open_expenses_count' => (int) ($expenseOpenRow->count ?? 0),
            ];

            $rows[$id] = $row;
        }
    }

    private function applyOccurrences(Collection &$rows, Tenant $tenant, array $scopeIds, Carbon $from, Carbon $to): void
    {
        if ($scopeIds === []) {
            return;
        }

        $period = $this->occurrenceBase($tenant, $scopeIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('condominium_id, count(*) as total')
            ->selectRaw("sum(case when priority in ('high', 'urgent') then 1 else 0 end) as high_priority")
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $current = $this->occurrenceBase($tenant, $scopeIds)
            ->selectRaw('condominium_id')
            ->selectRaw("sum(case when status != 'closed' then 1 else 0 end) as open")
            ->selectRaw("sum(case when status != 'closed' and due_at is not null and due_at < ? then 1 else 0 end) as sla_overdue", [now()])
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $closed = $this->occurrenceBase($tenant, $scopeIds)
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to])
            ->get(['id', 'condominium_id', 'created_at', 'closed_at'])
            ->groupBy('condominium_id')
            ->map(fn (Collection $items) => [
                'closed' => $items->count(),
                'avg_resolution_hours' => round($items
                    ->filter(fn (Occurrence $occurrence) => $occurrence->created_at && $occurrence->closed_at)
                    ->avg(fn (Occurrence $occurrence) => $occurrence->created_at->diffInHours($occurrence->closed_at)) ?? 0, 1),
            ]);

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $periodRow = $period[$id] ?? null;
            $currentRow = $current[$id] ?? null;
            $closedRow = $closed[$id] ?? ['closed' => 0, 'avg_resolution_hours' => 0];

            $row['occurrences'] = [
                'created' => (int) ($periodRow->total ?? 0),
                'open' => (int) ($currentRow->open ?? 0),
                'closed' => (int) $closedRow['closed'],
                'sla_overdue' => (int) ($currentRow->sla_overdue ?? 0),
                'high_priority' => (int) ($periodRow->high_priority ?? 0),
                'avg_resolution_hours' => (float) $closedRow['avg_resolution_hours'],
            ];

            $rows[$id] = $row;
        }
    }

    private function applyReservations(Collection &$rows, Tenant $tenant, array $scopeIds, Carbon $from, Carbon $to): void
    {
        if ($scopeIds === []) {
            return;
        }

        $reservations = $this->reservationBase($tenant, $scopeIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('condominium_id, count(*) as total')
            ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as pending")
            ->selectRaw("sum(case when status = 'approved' then 1 else 0 end) as approved")
            ->selectRaw("sum(case when status = 'rejected' then 1 else 0 end) as rejected")
            ->selectRaw("sum(case when status = 'cancelled' then 1 else 0 end) as cancelled")
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $reservation = $reservations[$id] ?? null;
            $row['reservations'] = [
                'total' => (int) ($reservation->total ?? 0),
                'pending' => (int) ($reservation->pending ?? 0),
                'approved' => (int) ($reservation->approved ?? 0),
                'rejected' => (int) ($reservation->rejected ?? 0),
                'cancelled' => (int) ($reservation->cancelled ?? 0),
            ];
            $rows[$id] = $row;
        }
    }

    private function applyMaintenance(Collection &$rows, Tenant $tenant, array $scopeIds, Carbon $from, Carbon $to): void
    {
        if ($scopeIds === []) {
            return;
        }

        $plans = $this->maintenanceBase($tenant, $scopeIds)
            ->where('is_active', true)
            ->get(['id', 'condominium_id', 'next_due_date', 'alert_days', 'is_active']);

        $planStats = $plans->groupBy('condominium_id')->map(function (Collection $items) {
            return [
                'active' => $items->count(),
                'overdue' => $items->filter(fn (MaintenancePlan $plan) => $plan->status === 'overdue')->count(),
                'due_soon' => $items->filter(fn (MaintenancePlan $plan) => $plan->status === 'due_soon')->count(),
            ];
        });

        $records = MaintenanceRecord::query()
            ->join('maintenance_plans', 'maintenance_plans.id', '=', 'maintenance_records.maintenance_plan_id')
            ->where('maintenance_records.tenant_id', $tenant->id)
            ->whereIn('maintenance_plans.condominium_id', $scopeIds)
            ->whereBetween('maintenance_records.done_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('maintenance_plans.condominium_id as condominium_id, count(*) as total, coalesce(sum(maintenance_records.cost), 0) as cost')
            ->groupBy('maintenance_plans.condominium_id')
            ->get()
            ->keyBy('condominium_id');

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $plan = $planStats[$id] ?? ['active' => 0, 'overdue' => 0, 'due_soon' => 0];
            $record = $records[$id] ?? null;
            $row['maintenance'] = [
                'active' => (int) $plan['active'],
                'overdue' => (int) $plan['overdue'],
                'due_soon' => (int) $plan['due_soon'],
                'executions' => (int) ($record->total ?? 0),
                'execution_cost' => (float) ($record->cost ?? 0),
            ];
            $rows[$id] = $row;
        }
    }

    private function applyWorks(Collection &$rows, Tenant $tenant, array $scopeIds, Carbon $from, Carbon $to): void
    {
        if ($scopeIds === []) {
            return;
        }

        $period = $this->workBase($tenant, $scopeIds)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('condominium_id, count(*) as total, coalesce(sum(budget_amount), 0) as budget')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $current = $this->workBase($tenant, $scopeIds)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('condominium_id')
            ->selectRaw("sum(case when status in ('planned', 'budgeting', 'approved', 'in_progress', 'paused') then 1 else 0 end) as active")
            ->selectRaw("sum(case when status not in ('completed', 'cancelled') and expected_end_date is not null and expected_end_date < ? then 1 else 0 end) as overdue", [today()->toDateString()])
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $completed = $this->workBase($tenant, $scopeIds)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('condominium_id, count(*) as completed, coalesce(sum(final_amount), 0) as final_amount, coalesce(sum(budget_amount), 0) as completed_budget')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $periodRow = $period[$id] ?? null;
            $currentRow = $current[$id] ?? null;
            $completedRow = $completed[$id] ?? null;
            $finalAmount = (float) ($completedRow->final_amount ?? 0);
            $completedBudget = (float) ($completedRow->completed_budget ?? 0);

            $row['works'] = [
                'created' => (int) ($periodRow->total ?? 0),
                'active' => (int) ($currentRow->active ?? 0),
                'overdue' => (int) ($currentRow->overdue ?? 0),
                'completed' => (int) ($completedRow->completed ?? 0),
                'budget_amount' => (float) ($periodRow->budget ?? 0),
                'final_amount' => $finalAmount,
                'variance' => round($finalAmount - $completedBudget, 2),
            ];
            $rows[$id] = $row;
        }
    }

    private function applyDocuments(Collection &$rows, Tenant $tenant, array $scopeIds, Carbon $from, Carbon $to): void
    {
        if ($scopeIds === []) {
            return;
        }

        $uploaded = $this->documentBase($tenant, $scopeIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('condominium_id, count(*) as total')
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $documents = $this->documentBase($tenant, $scopeIds)
            ->get(['id', 'condominium_id', 'valid_until', 'renewal_alert_days', 'is_current']);

        $documentStats = $documents->groupBy('condominium_id')->map(fn (Collection $items) => [
            'current' => $items->filter(fn (Document $document) => (bool) $document->is_current)->count(),
            'expired' => $items->filter(fn (Document $document) => $document->expiry_status === 'expired')->count(),
            'expiring' => $items->filter(fn (Document $document) => $document->expiry_status === 'expiring')->count(),
        ]);

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $upload = $uploaded[$id] ?? null;
            $stats = $documentStats[$id] ?? ['current' => 0, 'expired' => 0, 'expiring' => 0];
            $row['documents'] = [
                'uploaded' => (int) ($upload->total ?? 0),
                'current' => (int) $stats['current'],
                'expiring' => (int) $stats['expiring'],
                'expired' => (int) $stats['expired'],
            ];
            $rows[$id] = $row;
        }
    }

    private function applyQuotations(Collection &$rows, Tenant $tenant, array $scopeIds, Carbon $from, Carbon $to): void
    {
        if ($scopeIds === []) {
            return;
        }

        $period = $this->quotationBase($tenant, $scopeIds)
            ->whereBetween('quotations.created_at', [$from, $to])
            ->selectRaw('condominium_id, count(*) as total')
            ->selectRaw("sum(case when status = 'collecting' then 1 else 0 end) as collecting")
            ->groupBy('condominium_id')
            ->get()
            ->keyBy('condominium_id');

        $approved = $this->quotationBase($tenant, $scopeIds)
            ->leftJoin('quotation_proposals as qp', 'qp.id', '=', 'quotations.approved_proposal_id')
            ->where('quotations.status', 'approved')
            ->whereBetween('quotations.approved_at', [$from, $to])
            ->selectRaw('quotations.condominium_id as condominium_id, count(*) as approved, coalesce(sum(qp.amount), 0) as amount')
            ->groupBy('quotations.condominium_id')
            ->get()
            ->keyBy('condominium_id');

        foreach ($scopeIds as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $row = $rows[$id];
            $periodRow = $period[$id] ?? null;
            $approvedRow = $approved[$id] ?? null;
            $row['quotations'] = [
                'created' => (int) ($periodRow->total ?? 0),
                'collecting' => (int) ($periodRow->collecting ?? 0),
                'approved' => (int) ($approvedRow->approved ?? 0),
                'approved_amount' => (float) ($approvedRow->amount ?? 0),
            ];
            $rows[$id] = $row;
        }
    }

    private function monthly(Tenant $tenant, array $scopeIds, array $activeModules, Carbon $from, Carbon $to): array
    {
        if ($scopeIds === []) {
            return [];
        }

        $months = [];
        $cursor = $from->copy()->startOfMonth();

        while ($cursor->lte($to)) {
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();
            $month = [
                'month' => $cursor->format('Y-m'),
                'label' => $cursor->locale('pt_BR')->isoFormat('MMM/YYYY'),
                'charged' => 0.0,
                'received' => 0.0,
                'expenses' => 0.0,
                'occurrences' => 0,
                'reservations' => 0,
                'maintenance_executions' => 0,
                'works_completed' => 0,
            ];

            if (in_array('financial', $activeModules, true)) {
                $month['charged'] = (float) $this->chargeBase($tenant, $scopeIds)
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('amount');
                $month['received'] = (float) $this->chargeBase($tenant, $scopeIds)
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $end])
                    ->sum('paid_amount');
                $month['expenses'] = (float) $this->expenseBase($tenant, $scopeIds)
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $end])
                    ->sum('paid_amount');
            }

            if (in_array('occurrences', $activeModules, true)) {
                $month['occurrences'] = $this->occurrenceBase($tenant, $scopeIds)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();
            }

            if (in_array('reservations', $activeModules, true)) {
                $month['reservations'] = $this->reservationBase($tenant, $scopeIds)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->count();
            }

            if (in_array('maintenance', $activeModules, true)) {
                $month['maintenance_executions'] = MaintenanceRecord::query()
                    ->join('maintenance_plans', 'maintenance_plans.id', '=', 'maintenance_records.maintenance_plan_id')
                    ->where('maintenance_records.tenant_id', $tenant->id)
                    ->whereIn('maintenance_plans.condominium_id', $scopeIds)
                    ->whereBetween('maintenance_records.done_date', [$start->toDateString(), $end->toDateString()])
                    ->count();
            }

            if (in_array('works', $activeModules, true)) {
                $month['works_completed'] = $this->workBase($tenant, $scopeIds)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end])
                    ->count();
            }

            $months[] = $month;
            $cursor->addMonth();
        }

        return $months;
    }

    private function summary(Collection $rows, array $activeModules): array
    {
        return [
            'condominiums' => $rows->count(),
            'units' => $rows->sum('structure.units'),
            'occupied_units' => $rows->sum('structure.occupied_units'),
            'residents' => $rows->sum('structure.residents'),
            'active_modules' => $activeModules,
            'financial' => [
                'charged' => round((float) $rows->sum('financial.charged'), 2),
                'received' => round((float) $rows->sum('financial.received'), 2),
                'expenses_paid' => round((float) $rows->sum('financial.expenses_paid'), 2),
                'balance' => round((float) $rows->sum('financial.balance'), 2),
                'open_charges' => round((float) $rows->sum('financial.open_charges'), 2),
                'overdue_charges' => round((float) $rows->sum('financial.overdue_charges'), 2),
                'delinquent_units' => (int) $rows->sum('financial.delinquent_units'),
                'open_expenses' => round((float) $rows->sum('financial.open_expenses'), 2),
            ],
            'operations' => [
                'open_occurrences' => (int) $rows->sum('occurrences.open'),
                'sla_overdue' => (int) $rows->sum('occurrences.sla_overdue'),
                'pending_reservations' => (int) $rows->sum('reservations.pending'),
                'maintenance_overdue' => (int) $rows->sum('maintenance.overdue'),
                'maintenance_due_soon' => (int) $rows->sum('maintenance.due_soon'),
                'active_works' => (int) $rows->sum('works.active'),
                'works_overdue' => (int) $rows->sum('works.overdue'),
                'documents_expiring' => (int) $rows->sum('documents.expiring'),
                'documents_expired' => (int) $rows->sum('documents.expired'),
                'quotations_collecting' => (int) $rows->sum('quotations.collecting'),
            ],
            'risk' => [
                'high' => $rows->where('risk.level', 'Alto')->count(),
                'medium' => $rows->where('risk.level', 'Medio')->count(),
                'low' => $rows->where('risk.level', 'Baixo')->count(),
            ],
        ];
    }

    private function rankings(Collection $rows): array
    {
        return [
            'financial_risk' => $rows
                ->sortByDesc(fn (array $row) => $row['financial']['overdue_charges'])
                ->take(5)
                ->values()
                ->map(fn (array $row) => [
                    'condominium' => $row['condominium'],
                    'value' => $row['financial']['overdue_charges'],
                    'detail' => $row['financial']['delinquent_units'].' unidade(s)',
                ]),
            'operational_risk' => $rows
                ->sortByDesc(fn (array $row) => $row['risk']['score'])
                ->take(5)
                ->values()
                ->map(fn (array $row) => [
                    'condominium' => $row['condominium'],
                    'value' => $row['risk']['score'],
                    'detail' => $row['risk']['level'],
                ]),
            'expenses' => $rows
                ->sortByDesc(fn (array $row) => $row['financial']['expenses_paid'])
                ->take(5)
                ->values()
                ->map(fn (array $row) => [
                    'condominium' => $row['condominium'],
                    'value' => $row['financial']['expenses_paid'],
                    'detail' => 'contas pagas',
                ]),
        ];
    }

    private function finalizeRow(array $row): array
    {
        $score = 0;
        $score += min(40, (int) ceil($row['financial']['overdue_charges'] / 1000));
        $score += $row['occurrences']['sla_overdue'] * 8;
        $score += $row['maintenance']['overdue'] * 8;
        $score += $row['works']['overdue'] * 10;
        $score += $row['documents']['expired'] * 6;
        $score += $row['financial']['open_expenses_count'] * 2;
        $score = min(100, $score);

        $row['risk'] = [
            'score' => $score,
            'level' => $score >= 60 ? 'Alto' : ($score >= 25 ? 'Medio' : 'Baixo'),
        ];

        return $row;
    }

    private function financialDefaults(): array
    {
        return [
            'charged' => 0.0,
            'charged_count' => 0,
            'received' => 0.0,
            'expenses_paid' => 0.0,
            'balance' => 0.0,
            'open_charges' => 0.0,
            'open_charges_count' => 0,
            'overdue_charges' => 0.0,
            'delinquent_units' => 0,
            'open_expenses' => 0.0,
            'open_expenses_count' => 0,
        ];
    }

    private function occurrenceDefaults(): array
    {
        return [
            'created' => 0,
            'open' => 0,
            'closed' => 0,
            'sla_overdue' => 0,
            'high_priority' => 0,
            'avg_resolution_hours' => 0.0,
        ];
    }

    private function reservationDefaults(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'cancelled' => 0,
        ];
    }

    private function maintenanceDefaults(): array
    {
        return [
            'active' => 0,
            'overdue' => 0,
            'due_soon' => 0,
            'executions' => 0,
            'execution_cost' => 0.0,
        ];
    }

    private function workDefaults(): array
    {
        return [
            'created' => 0,
            'active' => 0,
            'overdue' => 0,
            'completed' => 0,
            'budget_amount' => 0.0,
            'final_amount' => 0.0,
            'variance' => 0.0,
        ];
    }

    private function documentDefaults(): array
    {
        return [
            'uploaded' => 0,
            'current' => 0,
            'expiring' => 0,
            'expired' => 0,
        ];
    }

    private function quotationDefaults(): array
    {
        return [
            'created' => 0,
            'collecting' => 0,
            'approved' => 0,
            'approved_amount' => 0.0,
        ];
    }

    private function chargeBase(Tenant $tenant, array $scopeIds): Builder
    {
        return Charge::where('tenant_id', $tenant->id)->whereIn('condominium_id', $scopeIds);
    }

    private function expenseBase(Tenant $tenant, array $scopeIds): Builder
    {
        return Expense::where('tenant_id', $tenant->id)->whereIn('condominium_id', $scopeIds);
    }

    private function occurrenceBase(Tenant $tenant, array $scopeIds): Builder
    {
        return Occurrence::where('tenant_id', $tenant->id)->whereIn('condominium_id', $scopeIds);
    }

    private function reservationBase(Tenant $tenant, array $scopeIds): Builder
    {
        return Reservation::where('tenant_id', $tenant->id)->whereIn('condominium_id', $scopeIds);
    }

    private function maintenanceBase(Tenant $tenant, array $scopeIds): Builder
    {
        return MaintenancePlan::where('tenant_id', $tenant->id)->whereIn('condominium_id', $scopeIds);
    }

    private function workBase(Tenant $tenant, array $scopeIds): Builder
    {
        return Work::where('tenant_id', $tenant->id)->whereIn('condominium_id', $scopeIds);
    }

    private function documentBase(Tenant $tenant, array $scopeIds): Builder
    {
        return Document::where('tenant_id', $tenant->id)->whereIn('condominium_id', $scopeIds);
    }

    private function quotationBase(Tenant $tenant, array $scopeIds): Builder
    {
        return Quotation::where('quotations.tenant_id', $tenant->id)->whereIn('quotations.condominium_id', $scopeIds);
    }
}
