<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Person;
use App\Models\CondominiumManager;
use App\Rules\CpfCnpj;
use App\Services\PlanLimitService;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CondominiumController extends Controller
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly StorageService $storage,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $condominiums = Condominium::where('tenant_id', $tenant->id)
            ->withCount(['blocks', 'units'])
            ->with(['activeManagers.person'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('city', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Condominiums/Index', [
            'condominiums' => $condominiums,
            'filters' => $request->only(['search', 'status']),
            'usage' => $this->planLimitService->getUsageSummary($tenant)['condominiums'],
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');
        $this->planLimitService->check($tenant, 'condominiums');

        return Inertia::render('Condominiums/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $this->planLimitService->check($tenant, 'condominiums');

        $request->merge(['cnpj' => preg_replace('/\D/', '', (string) $request->input('cnpj')) ?: null]);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'cnpj' => ["nullable", "string", "max:18", new CpfCnpj, "unique:condominiums,cnpj,NULL,id,tenant_id,{$tenant->id}"],
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'zip_code' => 'nullable|string|max:9',
            'street' => 'nullable|string|max:200',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
        ]);

        unset($data['logo']);

        $condo = Condominium::create(array_merge($data, ['tenant_id' => $tenant->id]));
        $this->syncLogo($request, $condo);

        $this->planLimitService->increment($tenant, 'condominiums');

        return redirect()->route('condominiums.show', $condo)->with('success', "Condomínio \"{$condo->name}\" criado com sucesso.");
    }

    public function show(Condominium $condominium): Response
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $condominium->load([
            'blocks' => fn ($q) => $q->withCount('units')->orderBy('name'),
            'activeManagers.person',
        ]);

        $unitStats = $condominium->units()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Condominiums/Show', [
            'condominium' => $condominium,
            'unitStats' => $unitStats,
            'persons' => Person::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'cpf']),
            'managerRoles' => CondominiumManager::roleLabels(),
        ]);
    }

    public function edit(Condominium $condominium): Response
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        return Inertia::render('Condominiums/Edit', [
            'condominium' => $condominium,
        ]);
    }

    public function update(Request $request, Condominium $condominium): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $request->merge(['cnpj' => preg_replace('/\D/', '', (string) $request->input('cnpj')) ?: null]);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'cnpj' => ["nullable", "string", "max:18", new CpfCnpj, "unique:condominiums,cnpj,{$condominium->id},id,tenant_id,{$tenant->id}"],
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
            'zip_code' => 'nullable|string|max:9',
            'street' => 'nullable|string|max:200',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'status' => 'in:active,inactive',
        ]);

        unset($data['logo'], $data['remove_logo']);

        $condominium->update($data);
        $this->syncLogo($request, $condominium);

        return redirect()->route('condominiums.show', $condominium)->with('success', 'Condomínio atualizado.');
    }

    public function destroy(Condominium $condominium): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $condominium->delete();
        $this->planLimitService->decrement($tenant, 'condominiums');

        return redirect()->route('condominiums.index')->with('success', 'Condomínio excluído.');
    }

    // --- Blocos ---

    public function storeBlock(Request $request, Condominium $condominium): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'floors' => 'nullable|integer|min:1|max:200',
        ]);

        $condominium->blocks()->create(array_merge($data, ['tenant_id' => $tenant->id]));

        return back()->with('success', "Bloco \"{$data['name']}\" adicionado.");
    }

    public function updateBlock(Request $request, Condominium $condominium, \App\Models\Block $block): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id && $block->condominium_id === $condominium->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'floors' => 'nullable|integer|min:1|max:200',
        ]);

        $block->update($data);

        return back()->with('success', 'Bloco atualizado.');
    }

    public function destroyBlock(Condominium $condominium, \App\Models\Block $block): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id && $block->condominium_id === $condominium->id, 403);
        abort_if($block->units()->exists(), 422, 'Remova as unidades do bloco antes de excluí-lo.');

        $block->delete();

        return back()->with('success', 'Bloco removido.');
    }

    // --- Gestores (síndico / conselheiro) ---

    public function storeManager(Request $request, Condominium $condominium): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'person_id' => 'required|uuid|exists:persons,id',
            'role' => 'required|in:sindico,subsindico,conselheiro',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Encerra mandato anterior do mesmo cargo no condomínio
        if (in_array($data['role'], ['sindico', 'subsindico'])) {
            $condominium->managers()
                ->where('role', $data['role'])
                ->whereNull('end_date')
                ->update(['end_date' => $data['start_date']]);
        }

        $condominium->managers()->create(array_merge($data, ['tenant_id' => $tenant->id]));

        return back()->with('success', 'Gestor adicionado.');
    }

    public function destroyManager(Condominium $condominium, CondominiumManager $manager): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id && $manager->condominium_id === $condominium->id, 403);

        $manager->update(['end_date' => now()->toDateString()]);

        return back()->with('success', 'Mandato encerrado.');
    }

    private function syncLogo(Request $request, Condominium $condominium): void
    {
        if (! $request->hasFile('logo') && ! $request->boolean('remove_logo')) {
            return;
        }

        $settings = $condominium->settings ?? [];

        if ($logo = $condominium->logoObject()) {
            $this->storage->delete($logo);
        }

        data_forget($settings, 'brand.logo_storage_object_id');
        data_forget($settings, 'brand.logo_url');

        if ($request->hasFile('logo')) {
            $logo = $this->storage->upload(
                file: $request->file('logo'),
                tenant: app('tenant'),
                entityType: Condominium::LOGO_ENTITY,
                entityId: $condominium->id,
                visibility: 'tenant',
                condominiumId: $condominium->id,
            );

            data_set($settings, 'brand.logo_storage_object_id', $logo->id);
        }

        $condominium->update(['settings' => $settings]);
    }
}
