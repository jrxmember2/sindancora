<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CommonArea;
use App\Models\Condominium;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommonAreaController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $areas = CommonArea::where('tenant_id', $tenant->id)
            ->with('condominium:id,name')
            ->withCount('reservations')
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('CommonAreas/Index', [
            'areas' => $areas,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'filters' => $request->only(['condominium_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CommonAreas/Create', [
            'condominiums' => $this->condominiumOptions(app('tenant')->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id);

        CommonArea::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return redirect()->route('areas.index')->with('success', 'Área comum criada.');
    }

    public function edit(CommonArea $area): Response
    {
        $area = $this->authorizeTenant($area);

        return Inertia::render('CommonAreas/Edit', [
            'area' => $area,
            'condominiums' => $this->condominiumOptions($area->tenant_id),
        ]);
    }

    public function update(Request $request, CommonArea $area): RedirectResponse
    {
        $area = $this->authorizeTenant($area);
        $data = $this->validated($request, $area->tenant_id);

        $area->update($data);

        return redirect()->route('areas.index')->with('success', 'Área comum atualizada.');
    }

    public function destroy(CommonArea $area): RedirectResponse
    {
        $area = $this->authorizeTenant($area);
        $area->delete();

        return redirect()->route('areas.index')->with('success', 'Área comum removida.');
    }

    private function validated(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'capacity' => 'nullable|integer|min:1',
            'requires_approval' => 'boolean',
            'min_advance_days' => 'required|integer|min:0|max:365',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => $request->filled('opening_time')
                ? 'nullable|date_format:H:i|after:opening_time'
                : 'nullable|date_format:H:i',
            'fee' => 'nullable|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'rules' => 'nullable|string|max:5000',
            'active' => 'boolean',
        ]);
    }

    private function authorizeTenant(CommonArea $area): CommonArea
    {
        abort_unless($area->tenant_id === app('tenant')->id, 403);

        return $area;
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }
}
