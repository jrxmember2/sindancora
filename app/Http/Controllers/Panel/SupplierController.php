<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Condominium;
use App\Models\Expense;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\QuotationProposal;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Rules\CpfCnpj;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $suppliers = Supplier::where('tenant_id', $tenant->id)
            ->withCount([
                'evaluations',
                'maintenancePlans as active_maintenance_plans_count' => fn ($q) => $q->where('is_active', true),
                'quotationProposals',
            ])
            ->withAvg('evaluations', 'score')
            ->withSum(['expenses as open_expenses_sum_amount' => fn ($q) => $q->open()], 'amount')
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->condominium_id, fn ($q, $id) => $q->whereHas('condominiums', fn ($c) => $c->where('condominiums.id', $id)))
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")
                    ->orWhere('document', 'like', '%'.preg_replace('/\D/', '', $s).'%'));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'categories' => $this->categoryOptions($tenant->id),
            'condominiums' => $this->condominiumOptions($tenant->id),
            'filters' => $request->only(['category', 'condominium_id', 'search']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Suppliers/Create', [
            'categories' => $this->categoryOptions($tenant->id),
            'condominiums' => $this->condominiumOptions($tenant->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id);

        $supplier = Supplier::create(array_merge(
            collect($data)->except('condominium_ids')->all(),
            ['tenant_id' => $tenant->id],
        ));
        $supplier->condominiums()->sync($data['condominium_ids'] ?? []);

        return redirect()->route('suppliers.index')->with('success', 'Fornecedor cadastrado.');
    }

    public function show(Supplier $supplier): Response
    {
        $supplier = $this->authorizeTenant($supplier);
        $supplier->load(['condominiums:id,name', 'evaluations.author:id,name'])
            ->loadAvg('evaluations', 'score')
            ->loadCount('evaluations');

        $maintenancePlans = MaintenancePlan::where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->with('condominium:id,name')
            ->orderBy('next_due_date')
            ->limit(6)
            ->get(['id', 'tenant_id', 'condominium_id', 'title', 'category', 'frequency', 'next_due_date', 'alert_days', 'is_active']);

        $maintenanceRecords = MaintenanceRecord::where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->with([
                'plan:id,condominium_id,title',
                'plan.condominium:id,name',
                'expense:id,maintenance_record_id,status,due_date,amount,description',
            ])
            ->latest('done_date')
            ->limit(6)
            ->get(['id', 'tenant_id', 'maintenance_plan_id', 'supplier_id', 'done_date', 'cost', 'notes']);

        $expenses = Expense::where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->with(['condominium:id,name', 'maintenanceRecord:id,maintenance_plan_id', 'maintenanceRecord.plan:id,title'])
            ->orderByRaw("CASE WHEN status = 'paid' THEN 2 WHEN status = 'cancelled' THEN 3 ELSE 1 END")
            ->orderBy('due_date')
            ->limit(8)
            ->get([
                'id', 'tenant_id', 'condominium_id', 'supplier_id', 'maintenance_record_id',
                'description', 'amount', 'status', 'due_date', 'paid_at', 'document_number',
            ]);

        $quotationProposals = QuotationProposal::where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->with(['quotation:id,condominium_id,title,status', 'quotation.condominium:id,name'])
            ->latest()
            ->limit(8)
            ->get(['id', 'tenant_id', 'quotation_id', 'supplier_id', 'amount', 'execution_days', 'valid_until', 'status']);

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'categories' => $this->categoryOptions($supplier->tenant_id),
            'maintenanceCategories' => Category::optionsFor($supplier->tenant_id, 'maintenance', MaintenancePlan::CATEGORIES),
            'supplierStats' => $this->supplierStats($supplier),
            'maintenancePlans' => $maintenancePlans,
            'maintenanceRecords' => $maintenanceRecords,
            'expenses' => $expenses,
            'quotationProposals' => $quotationProposals,
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        $supplier = $this->authorizeTenant($supplier);
        $supplier->load('condominiums:id');

        return Inertia::render('Suppliers/Edit', [
            'supplier' => array_merge($supplier->toArray(), [
                'condominium_ids' => $supplier->condominiums->pluck('id'),
            ]),
            'categories' => $this->categoryOptions($supplier->tenant_id),
            'condominiums' => $this->condominiumOptions($supplier->tenant_id),
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier = $this->authorizeTenant($supplier);
        $data = $this->validated($request, $supplier->tenant_id);

        $supplier->update(collect($data)->except('condominium_ids')->all());
        $supplier->condominiums()->sync($data['condominium_ids'] ?? []);

        return redirect()->route('suppliers.index')->with('success', 'Fornecedor atualizado.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier = $this->authorizeTenant($supplier);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Fornecedor removido.');
    }

    public function storeEvaluation(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier = $this->authorizeTenant($supplier);

        $data = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $supplier->evaluations()->create([
            'tenant_id' => $supplier->tenant_id,
            'user_id' => $request->user()->id,
            'score' => $data['score'],
            'comment' => $data['comment'] ?? null,
        ]);

        return back()->with('success', 'Avaliação registrada.');
    }

    public function destroyEvaluation(SupplierEvaluation $evaluation): RedirectResponse
    {
        abort_unless($evaluation->tenant_id === app('tenant')->id, 403);
        $evaluation->delete();

        return back()->with('success', 'Avaliação removida.');
    }

    private function validated(Request $request, string $tenantId): array
    {
        $request->merge(['document' => preg_replace('/\D/', '', (string) $request->input('document')) ?: null]);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'category' => ['nullable', 'string', Rule::in(array_keys($this->categoryOptions($tenantId)))],
            'document' => ['nullable', 'string', 'max:20', new CpfCnpj],
            'contact_name' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'website' => 'nullable|string|max:200',
            'zip_code' => 'nullable|string|max:9',
            'street' => 'nullable|string|max:200',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:150',
            'neighborhood' => 'nullable|string|max:150',
            'city' => 'nullable|string|max:150',
            'state' => 'nullable|string|max:2',
            'notes' => 'nullable|string|max:5000',
            'is_active' => 'boolean',
            'condominium_ids' => 'nullable|array',
            'condominium_ids.*' => "uuid|exists:condominiums,id,tenant_id,{$tenantId}",
        ]);

        return $data;
    }

    /** Mapa slug => rótulo das categorias de fornecedor (base + customizáveis do tenant). */
    private function categoryOptions(string $tenantId): array
    {
        return Category::optionsFor($tenantId, 'supplier', Supplier::CATEGORIES);
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }

    private function supplierStats(Supplier $supplier): array
    {
        return [
            'active_maintenance_plans' => MaintenancePlan::where('tenant_id', $supplier->tenant_id)
                ->where('supplier_id', $supplier->id)
                ->where('is_active', true)
                ->count(),
            'maintenance_records' => MaintenanceRecord::where('tenant_id', $supplier->tenant_id)
                ->where('supplier_id', $supplier->id)
                ->count(),
            'quotation_proposals' => QuotationProposal::where('tenant_id', $supplier->tenant_id)
                ->where('supplier_id', $supplier->id)
                ->count(),
            'open_expenses_total' => (float) Expense::where('tenant_id', $supplier->tenant_id)
                ->where('supplier_id', $supplier->id)
                ->open()
                ->sum('amount'),
            'overdue_expenses_total' => (float) Expense::where('tenant_id', $supplier->tenant_id)
                ->where('supplier_id', $supplier->id)
                ->open()
                ->whereDate('due_date', '<', today()->toDateString())
                ->sum('amount'),
            'paid_this_month' => (float) Expense::where('tenant_id', $supplier->tenant_id)
                ->where('supplier_id', $supplier->id)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('paid_amount'),
        ];
    }

    private function authorizeTenant(Supplier $supplier): Supplier
    {
        abort_unless($supplier->tenant_id === app('tenant')->id, 403);

        return $supplier;
    }
}
