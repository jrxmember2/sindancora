<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\Charge;
use App\Models\Condominium;
use App\Models\EmployeeVacationPeriod;
use App\Models\Expense;
use App\Models\MaintenancePlan;
use App\Models\Occurrence;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    private const SOURCES = [
        'reservations' => ['label' => 'Reservas', 'permission' => 'reservations:read', 'module' => 'reservations'],
        'assemblies' => ['label' => 'Assembleias', 'permission' => 'assemblies:read', 'module' => 'assemblies'],
        'maintenance' => ['label' => 'Manutencoes', 'permission' => 'maintenance:read', 'module' => 'maintenance'],
        'works' => ['label' => 'Obras/Reformas', 'permission' => 'works:read', 'module' => 'works'],
        'employee_vacations' => ['label' => 'Ferias', 'permission' => 'employees:read', 'module' => 'employees'],
        'occurrences' => ['label' => 'Ocorrencias', 'permission' => 'occurrences:read', 'module' => 'occurrences'],
        'expenses' => ['label' => 'Contas a pagar', 'permission' => 'expenses:read', 'module' => 'financial'],
        'charges' => ['label' => 'Cobrancas', 'permission' => 'charges:read', 'module' => 'financial'],
    ];

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $month = $this->parseMonth($request->input('month'));
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $condominiums = $this->accessibleCondominiums($tenant->id, $request->user());
        $condominiumIds = $condominiums->pluck('id')->all();
        $selectedCondominiumId = $this->selectedCondominiumId($request, $condominiumIds);
        $availableSources = $this->availableSources($request);
        $selectedSource = $this->selectedSource($request, $availableSources);

        $events = collect();

        if ($this->shouldLoad('reservations', $selectedSource, $availableSources)) {
            $events = $events->merge($this->reservationEvents($tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        if ($this->shouldLoad('assemblies', $selectedSource, $availableSources)) {
            $events = $events->merge($this->assemblyEvents($tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        if ($this->shouldLoad('maintenance', $selectedSource, $availableSources)) {
            $events = $events->merge($this->maintenanceEvents($tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        if ($this->shouldLoad('works', $selectedSource, $availableSources)) {
            $events = $events->merge($this->workEvents($tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        if ($this->shouldLoad('employee_vacations', $selectedSource, $availableSources)) {
            $events = $events->merge($this->employeeVacationEvents($tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        if ($this->shouldLoad('occurrences', $selectedSource, $availableSources)) {
            $events = $events->merge($this->occurrenceEvents($tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        if ($this->shouldLoad('expenses', $selectedSource, $availableSources)) {
            $events = $events->merge($this->expenseEvents($request, $tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        if ($this->shouldLoad('charges', $selectedSource, $availableSources)) {
            $events = $events->merge($this->chargeEvents($tenant->id, $start, $end, $condominiumIds, $selectedCondominiumId));
        }

        $events = $events
            ->sortBy('sort_key')
            ->values()
            ->map(function (array $event) {
                unset($event['sort_key']);

                return $event;
            });

        return Inertia::render('Schedule/Index', [
            'calendar' => [
                'month' => $month->format('Y-m'),
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'events' => $events,
            'summary' => $this->summary($events),
            'condominiums' => $condominiums->map(fn (Condominium $condominium) => [
                'value' => $condominium->id,
                'label' => $condominium->name,
            ])->values(),
            'sources' => collect($availableSources)->map(fn (array $source) => $source['label']),
            'filters' => [
                'condominium_id' => $selectedCondominiumId,
                'source' => $selectedSource,
            ],
        ]);
    }

    private function reservationEvents(string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        return Reservation::where('tenant_id', $tenantId)
            ->with(['condominium:id,name', 'commonArea:id,name'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['pending', 'approved'])
            ->when($condominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id), fn (Builder $query) => $query->whereIn('condominium_id', $condominiumIds))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get(['id', 'tenant_id', 'condominium_id', 'common_area_id', 'date', 'start_time', 'end_time', 'status'])
            ->map(fn (Reservation $reservation) => $this->event([
                'id' => 'reservation-'.$reservation->id,
                'record_id' => $reservation->id,
                'source' => 'reservations',
                'title' => $reservation->commonArea?->name ?? 'Reserva',
                'description' => $reservation->status === 'pending' ? 'Aguardando aprovacao' : 'Reserva aprovada',
                'date' => $reservation->date?->toDateString(),
                'time' => $this->hhmm($reservation->start_time),
                'end_time' => $this->hhmm($reservation->end_time),
                'status' => $reservation->status,
                'status_label' => Reservation::STATUSES[$reservation->status] ?? $reservation->status,
                'condominium' => $this->condominiumPayload($reservation->condominium),
                'url' => route('reservations.show', $reservation, false),
            ]));
    }

    private function assemblyEvents(string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        return Assembly::where('tenant_id', $tenantId)
            ->with('condominium:id,name')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($condominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id), fn (Builder $query) => $query->whereIn('condominium_id', $condominiumIds))
            ->orderBy('scheduled_at')
            ->get(['id', 'tenant_id', 'condominium_id', 'title', 'scheduled_at', 'status'])
            ->map(fn (Assembly $assembly) => $this->event([
                'id' => 'assembly-'.$assembly->id,
                'record_id' => $assembly->id,
                'source' => 'assemblies',
                'title' => $assembly->title,
                'description' => 'Assembleia digital',
                'date' => $assembly->scheduled_at?->toDateString(),
                'time' => $assembly->scheduled_at?->format('H:i'),
                'status' => $assembly->status,
                'status_label' => Assembly::STATUSES[$assembly->status] ?? $assembly->status,
                'condominium' => $this->condominiumPayload($assembly->condominium),
                'url' => route('assemblies.show', $assembly, false),
            ]));
    }

    private function maintenanceEvents(string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        return MaintenancePlan::where('tenant_id', $tenantId)
            ->with(['condominium:id,name', 'supplier:id,name'])
            ->where('is_active', true)
            ->whereBetween('next_due_date', [$start->toDateString(), $end->toDateString()])
            ->when($condominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id), fn (Builder $query) => $query->whereIn('condominium_id', $condominiumIds))
            ->orderBy('next_due_date')
            ->get(['id', 'tenant_id', 'condominium_id', 'supplier_id', 'title', 'category', 'frequency', 'next_due_date', 'alert_days', 'is_active'])
            ->map(fn (MaintenancePlan $plan) => $this->event([
                'id' => 'maintenance-'.$plan->id,
                'record_id' => $plan->id,
                'source' => 'maintenance',
                'title' => $plan->title,
                'description' => $plan->supplier?->name ? 'Fornecedor: '.$plan->supplier->name : 'Manutencao preventiva',
                'date' => $plan->next_due_date?->toDateString(),
                'status' => $plan->status,
                'status_label' => $this->maintenanceStatusLabel($plan->status, $plan->days_until_due),
                'condominium' => $this->condominiumPayload($plan->condominium),
                'url' => route('maintenance.show', $plan, false),
                'is_overdue' => $plan->status === 'overdue',
            ]));
    }

    private function workEvents(string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        $events = collect();

        $works = Work::where('tenant_id', $tenantId)
            ->with(['condominium:id,name', 'supplier:id,name'])
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('expected_end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('completed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            })
            ->when($condominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id), fn (Builder $query) => $query->whereIn('condominium_id', $condominiumIds))
            ->orderBy('start_date')
            ->get([
                'id', 'tenant_id', 'condominium_id', 'supplier_id', 'title', 'status', 'priority',
                'start_date', 'expected_end_date', 'completed_at', 'progress_percent', 'responsible_name',
            ]);

        foreach ($works as $work) {
            if ($this->dateInRange($work->start_date, $start, $end)) {
                $events->push($this->event([
                    'id' => 'work-start-'.$work->id,
                    'record_id' => $work->id,
                    'source' => 'works',
                    'title' => 'Inicio: '.$work->title,
                    'description' => $this->workDescription($work),
                    'date' => $work->start_date?->toDateString(),
                    'status' => $work->status,
                    'status_label' => $work->status_label,
                    'condominium' => $this->condominiumPayload($work->condominium),
                    'url' => route('works.show', $work, false),
                ]));
            }

            if ($this->dateInRange($work->expected_end_date, $start, $end)) {
                $events->push($this->event([
                    'id' => 'work-due-'.$work->id,
                    'record_id' => $work->id,
                    'source' => 'works',
                    'title' => 'Prazo: '.$work->title,
                    'description' => $this->workDescription($work),
                    'date' => $work->expected_end_date?->toDateString(),
                    'status' => $work->status,
                    'status_label' => $work->status_label,
                    'condominium' => $this->condominiumPayload($work->condominium),
                    'url' => route('works.show', $work, false),
                    'is_overdue' => $work->expected_end_date?->lt(today()) && ! in_array($work->status, ['completed', 'cancelled'], true),
                ]));
            }

            if ($this->dateTimeInRange($work->completed_at, $start, $end)) {
                $events->push($this->event([
                    'id' => 'work-completed-'.$work->id,
                    'record_id' => $work->id,
                    'source' => 'works',
                    'title' => 'Conclusao: '.$work->title,
                    'description' => $this->workDescription($work),
                    'date' => $work->completed_at?->toDateString(),
                    'time' => $work->completed_at?->format('H:i'),
                    'status' => $work->status,
                    'status_label' => $work->status_label,
                    'condominium' => $this->condominiumPayload($work->condominium),
                    'url' => route('works.show', $work, false),
                ]));
            }
        }

        return $events;
    }

    private function employeeVacationEvents(string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        return EmployeeVacationPeriod::where('tenant_id', $tenantId)
            ->with(['employee:id,tenant_id,condominium_id,name,position,vacation_alert_days', 'employee.condominium:id,name'])
            ->whereIn('status', ['pending', 'scheduled'])
            ->whereBetween('deadline_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('employee', function (Builder $query) use ($condominiumIds, $condominiumId) {
                $query->when($condominiumId, fn (Builder $employee, string $id) => $employee->where('condominium_id', $id), fn (Builder $employee) => $employee->whereIn('condominium_id', $condominiumIds));
            })
            ->orderBy('deadline_date')
            ->get(['id', 'tenant_id', 'employee_id', 'acquisition_start', 'acquisition_end', 'deadline_date', 'status', 'days'])
            ->map(fn (EmployeeVacationPeriod $period) => $this->event([
                'id' => 'employee-vacation-'.$period->id,
                'record_id' => $period->id,
                'source' => 'employee_vacations',
                'title' => 'Ferias: '.$period->employee?->name,
                'description' => trim(collect([
                    $period->employee?->position,
                    'Periodo aquisitivo '.$period->acquisition_start?->format('d/m/Y').' a '.$period->acquisition_end?->format('d/m/Y'),
                ])->filter()->implode(' | ')),
                'date' => $period->deadline_date?->toDateString(),
                'status' => $period->deadline_status ?? $period->status,
                'status_label' => $this->vacationStatusLabel($period),
                'condominium' => $this->condominiumPayload($period->employee?->condominium),
                'url' => $period->employee ? route('employees.show', $period->employee, false) : null,
                'is_overdue' => $period->deadline_status === 'overdue',
            ]));
    }

    private function occurrenceEvents(string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        return Occurrence::where('tenant_id', $tenantId)
            ->with(['condominium:id,name', 'unit:id,number', 'assignee:id,name'])
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($condominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id), fn (Builder $query) => $query->whereIn('condominium_id', $condominiumIds))
            ->orderBy('due_at')
            ->get(['id', 'tenant_id', 'condominium_id', 'unit_id', 'assigned_to', 'title', 'priority', 'status', 'due_at'])
            ->map(fn (Occurrence $occurrence) => $this->event([
                'id' => 'occurrence-'.$occurrence->id,
                'record_id' => $occurrence->id,
                'source' => 'occurrences',
                'title' => 'SLA: '.$occurrence->title,
                'description' => trim(collect([
                    $occurrence->unit?->number ? 'Unidade '.$occurrence->unit->number : null,
                    $occurrence->assignee?->name ? 'Responsavel: '.$occurrence->assignee->name : null,
                    'Prioridade: '.(Occurrence::PRIORITIES[$occurrence->priority] ?? $occurrence->priority),
                ])->filter()->implode(' | ')),
                'date' => $occurrence->due_at?->toDateString(),
                'time' => $occurrence->due_at?->format('H:i'),
                'status' => $occurrence->sla_status ?? $occurrence->status,
                'status_label' => $this->occurrenceStatusLabel($occurrence),
                'condominium' => $this->condominiumPayload($occurrence->condominium),
                'url' => route('occurrences.show', $occurrence, false),
                'is_overdue' => $occurrence->sla_status === 'overdue',
            ]));
    }

    private function expenseEvents(Request $request, string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        return Expense::where('tenant_id', $tenantId)
            ->with(['condominium:id,name', 'supplierRecord:id,name'])
            ->open()
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->when($condominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id), fn (Builder $query) => $query->whereIn('condominium_id', $condominiumIds))
            ->orderBy('due_date')
            ->get(['id', 'tenant_id', 'condominium_id', 'supplier_id', 'description', 'amount', 'due_date', 'status', 'supplier'])
            ->map(fn (Expense $expense) => $this->event([
                'id' => 'expense-'.$expense->id,
                'record_id' => $expense->id,
                'source' => 'expenses',
                'title' => 'Pagar: '.$expense->description,
                'description' => $expense->supplierRecord?->name ?? $expense->supplier,
                'date' => $expense->due_date?->toDateString(),
                'status' => $expense->display_status,
                'status_label' => $expense->display_status_label,
                'condominium' => $this->condominiumPayload($expense->condominium),
                'url' => $request->user()?->hasPermission('expenses:update')
                    ? route('expenses.edit', $expense, false)
                    : route('expenses.index', ['from' => $expense->due_date?->toDateString(), 'to' => $expense->due_date?->toDateString()], false),
                'amount' => (float) $expense->amount,
                'is_overdue' => $expense->display_status === 'overdue',
            ]));
    }

    private function chargeEvents(string $tenantId, Carbon $start, Carbon $end, array $condominiumIds, ?string $condominiumId): Collection
    {
        return Charge::where('tenant_id', $tenantId)
            ->with(['condominium:id,name', 'unit:id,number', 'person:id,name'])
            ->open()
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->when($condominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id), fn (Builder $query) => $query->whereIn('condominium_id', $condominiumIds))
            ->orderBy('due_date')
            ->get(['id', 'tenant_id', 'condominium_id', 'unit_id', 'person_id', 'description', 'amount', 'due_date', 'status'])
            ->map(fn (Charge $charge) => $this->event([
                'id' => 'charge-'.$charge->id,
                'record_id' => $charge->id,
                'source' => 'charges',
                'title' => 'Cobrar: '.$charge->description,
                'description' => trim(collect([
                    $charge->unit?->number ? 'Unidade '.$charge->unit->number : null,
                    $charge->person?->name,
                ])->filter()->implode(' | ')),
                'date' => $charge->due_date?->toDateString(),
                'status' => $charge->isOverdue() ? 'overdue' : $charge->status,
                'status_label' => $charge->isOverdue() ? 'Vencido' : (Charge::STATUSES[$charge->status] ?? $charge->status),
                'condominium' => $this->condominiumPayload($charge->condominium),
                'url' => route('charges.show', $charge, false),
                'amount' => (float) $charge->amount,
                'is_overdue' => $charge->isOverdue(),
            ]));
    }

    private function event(array $data): array
    {
        $source = $data['source'];
        $date = $data['date'] ?: now()->toDateString();
        $time = $data['time'] ?? null;

        return [
            'id' => $data['id'],
            'record_id' => $data['record_id'],
            'source' => $source,
            'source_label' => self::SOURCES[$source]['label'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'date' => $date,
            'time' => $time,
            'end_time' => $data['end_time'] ?? null,
            'status' => $data['status'] ?? null,
            'status_label' => $data['status_label'] ?? null,
            'condominium' => $data['condominium'] ?? null,
            'url' => $data['url'] ?? null,
            'amount' => $data['amount'] ?? null,
            'is_overdue' => (bool) ($data['is_overdue'] ?? false),
            'sort_key' => $date.' '.($time ?: '00:00').' '.$source.' '.$data['id'],
        ];
    }

    private function availableSources(Request $request): array
    {
        return collect(self::SOURCES)
            ->filter(fn (array $source) => $this->canSeeSource($request, $source['permission'], $source['module']))
            ->all();
    }

    private function canSeeSource(Request $request, string $permission, string $module): bool
    {
        return $request->user()?->hasPermission($permission) && $this->planAllows($module, $request);
    }

    private function planAllows(string $module, Request $request): bool
    {
        if ($request->user()?->isSuperAdmin()) {
            return true;
        }

        return (bool) app('tenant')->activePlan()?->hasModule($module);
    }

    private function selectedSource(Request $request, array $availableSources): ?string
    {
        $source = $request->input('source');

        return is_string($source) && isset($availableSources[$source]) ? $source : null;
    }

    private function shouldLoad(string $source, ?string $selectedSource, array $availableSources): bool
    {
        return isset($availableSources[$source]) && (! $selectedSource || $selectedSource === $source);
    }

    private function accessibleCondominiums(string $tenantId, ?User $user): Collection
    {
        $query = Condominium::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name');

        if (! $user?->isSuperAdmin() && ! $this->hasTenantWideCondominiumAccess($user)) {
            $ids = $user?->userRoles()
                ->whereNotNull('condominium_id')
                ->pluck('condominium_id')
                ->unique()
                ->values()
                ->all() ?? [];

            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'name']);
    }

    private function hasTenantWideCondominiumAccess(?User $user): bool
    {
        return (bool) ($user?->isSuperAdmin() || $user?->userRoles()->whereNull('condominium_id')->exists());
    }

    private function selectedCondominiumId(Request $request, array $condominiumIds): ?string
    {
        $id = $request->input('condominium_id');

        return is_string($id) && in_array($id, $condominiumIds, true) ? $id : null;
    }

    private function condominiumPayload(?Condominium $condominium): ?array
    {
        if (! $condominium) {
            return null;
        }

        return ['id' => $condominium->id, 'name' => $condominium->name];
    }

    private function summary(Collection $events): array
    {
        $today = today();
        $nextWeek = $today->copy()->addDays(7);

        return [
            'total' => $events->count(),
            'today' => $events->where('date', $today->toDateString())->count(),
            'next_7_days' => $events->filter(fn (array $event) => Carbon::parse($event['date'])->betweenIncluded($today, $nextWeek))->count(),
            'overdue' => $events->where('is_overdue', true)->count(),
            'by_source' => $events->groupBy('source')->map->count(),
        ];
    }

    private function parseMonth(?string $month): Carbon
    {
        try {
            return $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function hhmm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function dateInRange($date, Carbon $start, Carbon $end): bool
    {
        return $date && $date->betweenIncluded($start->copy()->startOfDay(), $end->copy()->endOfDay());
    }

    private function dateTimeInRange($date, Carbon $start, Carbon $end): bool
    {
        return $date && $date->betweenIncluded($start->copy()->startOfDay(), $end->copy()->endOfDay());
    }

    private function maintenanceStatusLabel(?string $status, ?int $days): string
    {
        return match ($status) {
            'overdue' => $days === null ? 'Atrasada' : 'Atrasada ha '.abs($days).'d',
            'due_soon' => $days === 0 ? 'Vence hoje' : 'Vence em '.$days.'d',
            'ok' => 'Em dia',
            default => 'Sem status',
        };
    }

    private function occurrenceStatusLabel(Occurrence $occurrence): string
    {
        return match ($occurrence->sla_status) {
            'overdue' => 'SLA vencido',
            'due_soon' => 'SLA vence em 24h',
            'on_time' => 'No prazo',
            default => Occurrence::STATUSES[$occurrence->status] ?? $occurrence->status,
        };
    }

    private function vacationStatusLabel(EmployeeVacationPeriod $period): string
    {
        return match ($period->deadline_status) {
            'overdue' => 'Atrasada ha '.abs($period->days_until_deadline ?? 0).'d',
            'due_soon' => ($period->days_until_deadline ?? 0) === 0 ? 'Vence hoje' : 'Vence em '.$period->days_until_deadline.'d',
            'ok' => EmployeeVacationPeriod::STATUSES[$period->status] ?? $period->status,
            default => EmployeeVacationPeriod::STATUSES[$period->status] ?? $period->status,
        };
    }

    private function workDescription(Work $work): string
    {
        return trim(collect([
            $work->supplier?->name ? 'Fornecedor: '.$work->supplier->name : null,
            $work->responsible_name ? 'Responsavel: '.$work->responsible_name : null,
            $work->progress_percent !== null ? $work->progress_percent.'% concluido' : null,
        ])->filter()->implode(' | '));
    }
}
