<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Condominium;
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
            ->withCount('evaluations')
            ->withAvg('evaluations', 'score')
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

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'categories' => $this->categoryOptions($supplier->tenant_id),
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

    private function authorizeTenant(Supplier $supplier): Supplier
    {
        abort_unless($supplier->tenant_id === app('tenant')->id, 403);

        return $supplier;
    }
}
