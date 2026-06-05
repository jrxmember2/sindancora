<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setores de atendimento por condomínio (Portaria, ADM, etc.): CRUD + membros (atendentes) +
 * horário de funcionamento e mensagem de fora de expediente. Permissão sectors:manage.
 */
class SectorController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        $sectors = Sector::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'members:id,name'])
            ->orderBy('condominium_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Sector $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'condominium_id' => $s->condominium_id,
                'condominium' => $s->condominium?->name,
                'is_active' => $s->is_active,
                'office_hours' => $s->office_hours,
                'away_message' => $s->away_message,
                'sort_order' => $s->sort_order,
                'member_ids' => $s->members->pluck('id'),
                'members' => $s->members->pluck('name'),
            ]);

        return Inertia::render('Settings/Sectors', [
            'sectors' => $sectors,
            'condominiums' => Condominium::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'users' => User::where('tenant_id', $tenant->id)->active()->whereNull('person_id')
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name]),
            'weekdays' => Sector::WEEKDAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validateData($request, $tenant->id);

        Sector::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return back()->with('success', 'Setor criado.');
    }

    public function update(Request $request, Sector $sector): RedirectResponse
    {
        $this->authorizeTenant($sector);
        $data = $this->validateData($request, $sector->tenant_id);

        $sector->update($data);

        return back()->with('success', 'Setor atualizado.');
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        $this->authorizeTenant($sector);
        $sector->delete();

        return back()->with('success', 'Setor removido.');
    }

    /** Sincroniza os atendentes (membros) do setor. */
    public function syncMembers(Request $request, Sector $sector): RedirectResponse
    {
        $this->authorizeTenant($sector);

        $data = $request->validate([
            'user_ids' => 'array',
            'user_ids.*' => 'uuid',
        ]);

        $valid = User::where('tenant_id', $sector->tenant_id)
            ->whereIn('id', $data['user_ids'] ?? [])
            ->pluck('id');

        $sector->members()->sync($valid);

        return back()->with('success', 'Atendentes atualizados.');
    }

    private function validateData(Request $request, string $tenantId): array
    {
        $data = $request->validate([
            'condominium_id' => ['required', 'uuid', Rule::exists('condominiums', 'id')->where('tenant_id', $tenantId)],
            'name' => 'required|string|max:80',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'away_message' => 'nullable|string|max:1000',
            'office_hours' => 'nullable|array',
            'office_hours.*.enabled' => 'boolean',
            'office_hours.*.open' => 'nullable|date_format:H:i',
            'office_hours.*.close' => 'nullable|date_format:H:i',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function authorizeTenant(Sector $sector): void
    {
        abort_unless($sector->tenant_id === app('tenant')->id, 403);
    }
}
