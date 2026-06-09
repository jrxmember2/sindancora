<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Employee;
use App\Models\EmployeeVacationPeriod;
use App\Models\Person;
use App\Models\User;
use App\Rules\CpfCnpj;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiums = $this->accessibleCondominiums($tenant->id, $request->user());
        $condominiumIds = $condominiums->pluck('id')->all();
        $selectedCondominiumId = $this->selectedCondominiumId($request, $condominiumIds);

        $baseQuery = $this->filteredEmployeeQuery($request, $tenant->id, $condominiumIds, $selectedCondominiumId);

        $employees = (clone $baseQuery)
            ->with([
                'condominium:id,name',
                'openVacationPeriods' => fn ($query) => $query->select([
                    'id', 'tenant_id', 'employee_id', 'acquisition_start', 'acquisition_end',
                    'deadline_date', 'vacation_start', 'vacation_end', 'days', 'status',
                ]),
            ])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'summary' => $this->summary($baseQuery),
            'condominiums' => $this->condominiumOptions($condominiums),
            'statuses' => Employee::STATUSES,
            'employmentTypes' => Employee::EMPLOYMENT_TYPES,
            'filters' => [
                ...$request->only(['search', 'status', 'vacation_status']),
                'condominium_id' => $selectedCondominiumId,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiums = $this->accessibleCondominiums($tenant->id, $request->user());

        return Inertia::render('Employees/Create', [
            'condominiums' => $this->condominiumOptions($condominiums),
            'persons' => $this->personOptions($tenant->id),
            'statuses' => Employee::STATUSES,
            'employmentTypes' => Employee::EMPLOYMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validatedEmployee($request, $tenant->id);
        $createInitialVacation = $request->boolean('create_initial_vacation_period', true);

        $employee = Employee::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()?->id,
        ]));

        if ($createInitialVacation) {
            $this->createInitialVacationPeriod($employee);
        }

        return redirect()->route('employees.show', $employee)->with('success', 'Funcionario cadastrado.');
    }

    public function show(Request $request, Employee $employee): Response
    {
        $employee = $this->authorizeEmployee($employee, $request);
        $employee->load([
            'condominium:id,name',
            'person:id,name,cpf,email,phone',
            'creator:id,name',
            'vacationPeriods',
        ]);

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'vacationStatuses' => EmployeeVacationPeriod::STATUSES,
        ]);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $employee = $this->authorizeEmployee($employee, $request);
        $tenant = app('tenant');

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            'condominiums' => $this->condominiumOptions($this->accessibleCondominiums($tenant->id, $request->user())),
            'persons' => $this->personOptions($tenant->id),
            'statuses' => Employee::STATUSES,
            'employmentTypes' => Employee::EMPLOYMENT_TYPES,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $employee = $this->authorizeEmployee($employee, $request);
        $employee->update($this->validatedEmployee($request, $employee->tenant_id));

        return redirect()->route('employees.show', $employee)->with('success', 'Funcionario atualizado.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $employee = $this->authorizeEmployee($employee, $request);
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Funcionario removido.');
    }

    public function storeVacationPeriod(Request $request, Employee $employee): RedirectResponse
    {
        $employee = $this->authorizeEmployee($employee, $request);

        $employee->vacationPeriods()->create(array_merge(
            $this->validatedVacationPeriod($request),
            ['tenant_id' => $employee->tenant_id],
        ));

        return back()->with('success', 'Periodo de ferias registrado.');
    }

    public function updateVacationPeriod(Request $request, EmployeeVacationPeriod $period): RedirectResponse
    {
        $period = $this->authorizeVacationPeriod($period, $request);
        $period->fill($this->validatedVacationPeriod($request));

        if ($period->isDirty(['deadline_date', 'status']) && in_array($period->status, ['pending', 'scheduled'], true)) {
            $period->notified_at = null;
        }

        $period->save();

        return back()->with('success', 'Periodo de ferias atualizado.');
    }

    public function destroyVacationPeriod(Request $request, EmployeeVacationPeriod $period): RedirectResponse
    {
        $period = $this->authorizeVacationPeriod($period, $request);
        $period->delete();

        return back()->with('success', 'Periodo de ferias removido.');
    }

    private function filteredEmployeeQuery(Request $request, string $tenantId, array $condominiumIds, ?string $selectedCondominiumId): Builder
    {
        return Employee::where('tenant_id', $tenantId)
            ->whereIn('condominium_id', $condominiumIds)
            ->when($selectedCondominiumId, fn (Builder $query, string $id) => $query->where('condominium_id', $id))
            ->when($request->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->search, function (Builder $query, string $search) {
                $document = preg_replace('/\D/', '', $search);

                $query->where(function (Builder $where) use ($search, $document) {
                    $where->where('name', 'ilike', "%{$search}%")
                        ->orWhere('position', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");

                    if ($document) {
                        $where->orWhere('document', 'like', "%{$document}%");
                    }
                });
            })
            ->when($request->vacation_status === 'overdue', fn (Builder $query) => $query->whereHas('vacationPeriods', function (Builder $period) {
                $period->whereIn('status', ['pending', 'scheduled'])
                    ->whereRaw('deadline_date < CURRENT_DATE');
            }))
            ->when($request->vacation_status === 'due_soon', fn (Builder $query) => $query->whereHas('vacationPeriods', function (Builder $period) {
                $period->whereIn('status', ['pending', 'scheduled'])
                    ->whereRaw('deadline_date >= CURRENT_DATE')
                    ->whereRaw('employee_vacation_periods.deadline_date <= (CURRENT_DATE + COALESCE(employees.vacation_alert_days, 60) * INTERVAL \'1 day\')');
            }));
    }

    private function summary(Builder $baseQuery): array
    {
        return [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'due_soon' => (clone $baseQuery)->whereHas('vacationPeriods', function (Builder $period) {
                $period->whereIn('status', ['pending', 'scheduled'])
                    ->whereRaw('deadline_date >= CURRENT_DATE')
                    ->whereRaw('employee_vacation_periods.deadline_date <= (CURRENT_DATE + COALESCE(employees.vacation_alert_days, 60) * INTERVAL \'1 day\')');
            })->count(),
            'overdue' => (clone $baseQuery)->whereHas('vacationPeriods', function (Builder $period) {
                $period->whereIn('status', ['pending', 'scheduled'])
                    ->whereRaw('deadline_date < CURRENT_DATE');
            })->count(),
        ];
    }

    private function validatedEmployee(Request $request, string $tenantId): array
    {
        $request->merge([
            'document' => preg_replace('/\D/', '', (string) $request->input('document')) ?: null,
            'phone' => preg_replace('/[^\d+]/', '', (string) $request->input('phone')) ?: null,
        ]);

        $condominiumIds = $this->accessibleCondominiums($tenantId, $request->user())->pluck('id')->all();

        $data = $request->validate([
            'condominium_id' => ['required', 'uuid', Rule::in($condominiumIds)],
            'person_id' => ['nullable', 'uuid', "exists:persons,id,tenant_id,{$tenantId}"],
            'name' => 'required|string|max:150',
            'document' => ['nullable', 'string', 'max:20', new CpfCnpj],
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'position' => 'nullable|string|max:100',
            'employment_type' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'admission_date' => 'required|date',
            'termination_date' => 'nullable|date|after_or_equal:admission_date',
            'ctps_number' => 'nullable|string|max:40',
            'pis_pasep' => 'nullable|string|max:40',
            'salary' => 'nullable|numeric|min:0|max:999999999.99',
            'vacation_alert_days' => 'nullable|integer|min:0|max:365',
            'notes' => 'nullable|string|max:5000',
        ]);

        $data['vacation_alert_days'] ??= 60;

        return $data;
    }

    private function validatedVacationPeriod(Request $request): array
    {
        return $request->validate([
            'acquisition_start' => 'required|date',
            'acquisition_end' => 'required|date|after_or_equal:acquisition_start',
            'deadline_date' => 'required|date|after_or_equal:acquisition_end',
            'vacation_start' => 'nullable|date',
            'vacation_end' => 'nullable|date|after_or_equal:vacation_start',
            'days' => 'required|integer|min:1|max:30',
            'status' => ['required', Rule::in(array_keys(EmployeeVacationPeriod::STATUSES))],
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function createInitialVacationPeriod(Employee $employee): void
    {
        $start = Carbon::parse($employee->admission_date)->startOfDay();
        $end = $start->copy()->addYear()->subDay();

        $employee->vacationPeriods()->create([
            'tenant_id' => $employee->tenant_id,
            'acquisition_start' => $start->toDateString(),
            'acquisition_end' => $end->toDateString(),
            'deadline_date' => $end->copy()->addYear()->toDateString(),
            'days' => 30,
            'status' => 'pending',
        ]);
    }

    private function authorizeEmployee(Employee $employee, Request $request): Employee
    {
        abort_unless($employee->tenant_id === app('tenant')->id, 403);

        $allowedIds = $this->accessibleCondominiums($employee->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($employee->condominium_id, $allowedIds, true), 403);

        return $employee;
    }

    private function authorizeVacationPeriod(EmployeeVacationPeriod $period, Request $request): EmployeeVacationPeriod
    {
        abort_unless($period->tenant_id === app('tenant')->id, 403);
        $period->loadMissing('employee');
        $this->authorizeEmployee($period->employee, $request);

        return $period;
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

    private function condominiumOptions(Collection $condominiums): Collection
    {
        return $condominiums
            ->map(fn (Condominium $condominium) => ['value' => $condominium->id, 'label' => $condominium->name])
            ->values();
    }

    private function personOptions(string $tenantId): Collection
    {
        return Person::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'cpf', 'email', 'phone'])
            ->map(fn (Person $person) => [
                'value' => $person->id,
                'label' => $person->name,
                'document' => $person->cpf,
                'email' => $person->email,
                'phone' => $person->phone,
            ])
            ->values();
    }
}
