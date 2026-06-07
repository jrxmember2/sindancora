<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Condominium;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\Supplier;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $service) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $query = MaintenancePlan::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'supplier:id,name'])
            ->withCount('records')
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c));

        // Filtro por situação (calculada em SQL com a mesma regra dos accessors).
        if ($request->status === 'overdue') {
            $query->whereRaw('next_due_date < CURRENT_DATE');
        } elseif ($request->status === 'due_soon') {
            $query->whereRaw('next_due_date >= CURRENT_DATE')
                ->whereRaw('next_due_date <= (CURRENT_DATE + COALESCE(alert_days, 15) * INTERVAL \'1 day\')');
        }

        $plans = $query->orderBy('next_due_date')->paginate(20)->withQueryString();

        $overdueCount = MaintenancePlan::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereRaw('next_due_date < CURRENT_DATE')
            ->count();

        return Inertia::render('Maintenance/Index', [
            'plans' => $plans,
            'overdueCount' => $overdueCount,
            'categories' => $this->categoryOptions($tenant->id),
            'condominiums' => $this->condominiumOptions($tenant->id),
            'frequencies' => MaintenancePlan::FREQUENCIES,
            'filters' => $request->only(['condominium_id', 'category', 'status']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Maintenance/Create', [
            'categories' => $this->categoryOptions($tenant->id),
            'condominiums' => $this->condominiumOptions($tenant->id),
            'suppliers' => $this->supplierOptions($tenant->id),
            'frequencies' => MaintenancePlan::FREQUENCIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id);

        MaintenancePlan::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return redirect()->route('maintenance.index')->with('success', 'Manutenção cadastrada.');
    }

    public function show(Request $request, MaintenancePlan $maintenance): Response
    {
        $maintenance = $this->authorizeTenant($maintenance);
        $maintenance->load([
            'condominium:id,name',
            'supplier:id,name',
            'records.supplier:id,name',
            'records.author:id,name',
            'records.expense:id,maintenance_record_id,status,due_date,amount,description',
        ]);

        return Inertia::render('Maintenance/Show', [
            'plan' => $maintenance,
            'categories' => $this->categoryOptions($maintenance->tenant_id),
            'frequencies' => MaintenancePlan::FREQUENCIES,
            'suppliers' => $this->supplierOptions($maintenance->tenant_id),
            'canGenerateExpense' => $this->canGenerateExpense($request),
        ]);
    }

    public function edit(MaintenancePlan $maintenance): Response
    {
        $maintenance = $this->authorizeTenant($maintenance);

        return Inertia::render('Maintenance/Edit', [
            'plan' => $maintenance,
            'categories' => $this->categoryOptions($maintenance->tenant_id),
            'condominiums' => $this->condominiumOptions($maintenance->tenant_id),
            'suppliers' => $this->supplierOptions($maintenance->tenant_id),
            'frequencies' => MaintenancePlan::FREQUENCIES,
        ]);
    }

    public function update(Request $request, MaintenancePlan $maintenance): RedirectResponse
    {
        $maintenance = $this->authorizeTenant($maintenance);
        $data = $this->validated($request, $maintenance->tenant_id);

        $maintenance->update($data);

        return redirect()->route('maintenance.index')->with('success', 'Manutenção atualizada.');
    }

    public function destroy(MaintenancePlan $maintenance): RedirectResponse
    {
        $maintenance = $this->authorizeTenant($maintenance);
        $maintenance->delete();

        return redirect()->route('maintenance.index')->with('success', 'Manutenção removida.');
    }

    public function registerExecution(Request $request, MaintenancePlan $maintenance): RedirectResponse
    {
        $maintenance = $this->authorizeTenant($maintenance);

        $data = $request->validate([
            'done_date' => 'required|date',
            'supplier_id' => ['nullable', 'uuid', "exists:suppliers,id,tenant_id,{$maintenance->tenant_id}"],
            'cost' => [
                Rule::requiredIf($request->boolean('generate_expense')),
                'nullable',
                'numeric',
                $request->boolean('generate_expense') ? 'min:0.01' : 'min:0',
            ],
            'notes' => 'nullable|string|max:2000',
            'generate_expense' => 'boolean',
            'expense_due_date' => [Rule::requiredIf($request->boolean('generate_expense')), 'nullable', 'date'],
            'expense_document_number' => 'nullable|string|max:80',
            'expense_reminder_days' => 'nullable|integer|min:0|max:60',
        ]);

        $data['generate_expense'] = $request->boolean('generate_expense');

        if ($data['generate_expense']) {
            abort_unless($this->canGenerateExpense($request), 403);
        }

        $this->service->registerExecution($maintenance, $data, $request->user());

        return back()->with('success', 'Execução registrada. Próxima data atualizada.');
    }

    public function destroyRecord(MaintenanceRecord $record): RedirectResponse
    {
        abort_unless($record->tenant_id === app('tenant')->id, 403);
        $record->delete();

        return back()->with('success', 'Execução removida.');
    }

    private function validated(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'supplier_id' => ['nullable', 'uuid', "exists:suppliers,id,tenant_id,{$tenantId}"],
            'category' => ['nullable', 'string', Rule::in(array_keys($this->categoryOptions($tenantId)))],
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'frequency' => ['required', Rule::in(array_keys(MaintenancePlan::FREQUENCIES))],
            'next_due_date' => 'required|date',
            'alert_days' => 'nullable|integer|min:0|max:365',
            'is_active' => 'boolean',
        ]);
    }

    private function categoryOptions(string $tenantId): array
    {
        return Category::optionsFor($tenantId, 'maintenance', MaintenancePlan::CATEGORIES);
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }

    private function supplierOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Supplier::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]);
    }

    private function canGenerateExpense(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->hasPermission('expenses:create')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (bool) app('tenant')->activePlan()?->hasModule('financial');
    }

    private function authorizeTenant(MaintenancePlan $maintenance): MaintenancePlan
    {
        abort_unless($maintenance->tenant_id === app('tenant')->id, 403);

        return $maintenance;
    }
}
