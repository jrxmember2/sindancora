<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonUnitLink;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $persons = Person::where('tenant_id', $tenant->id)
            ->with(['activeLinks.unit.condominium'])
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('cpf', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Persons/Index', [
            'persons' => $persons,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Persons/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $this->validated($request, $tenant->id);

        $person = Person::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return redirect()->route('persons.show', $person)->with('success', "Pessoa \"{$person->name}\" cadastrada.");
    }

    public function show(Person $person): Response
    {
        $tenant = app('tenant');
        abort_unless($person->tenant_id === $tenant->id, 403);

        $person->load([
            'unitLinks' => fn ($q) => $q->with('unit.condominium', 'unit.block')->orderByDesc('start_date'),
        ]);

        $availableUnits = Unit::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'block:id,name'])
            ->orderBy('condominium_id')
            ->orderBy('number')
            ->get(['id', 'condominium_id', 'block_id', 'number', 'type', 'status']);

        return Inertia::render('Persons/Show', [
            'person' => $person,
            'linkTypes' => PersonUnitLink::typeLabels(),
            'availableUnits' => $availableUnits,
        ]);
    }

    public function edit(Person $person): Response
    {
        $tenant = app('tenant');
        abort_unless($person->tenant_id === $tenant->id, 403);

        return Inertia::render('Persons/Edit', ['person' => $person]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($person->tenant_id === $tenant->id, 403);

        $data = $this->validated($request, $tenant->id, $person->id);
        $person->update($data);

        return redirect()->route('persons.show', $person)->with('success', 'Cadastro atualizado.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($person->tenant_id === $tenant->id, 403);

        $person->delete();

        return redirect()->route('persons.index')->with('success', 'Pessoa excluída.');
    }

    // --- Vínculos com unidades ---

    public function storeLink(Request $request, Person $person): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($person->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'unit_id' => 'required|uuid|exists:units,id',
            'type' => 'required|in:owner,tenant,resident,dependent',
            'is_primary' => 'boolean',
            'start_date' => 'required|date',
        ]);

        // Se is_primary, remove primary dos outros vínculos ativos nesta unidade
        if (! empty($data['is_primary'])) {
            PersonUnitLink::where('unit_id', $data['unit_id'])
                ->whereNull('end_date')
                ->update(['is_primary' => false]);
        }

        $person->unitLinks()->create(array_merge($data, ['tenant_id' => $tenant->id]));

        // Atualiza status da unidade para occupied
        Unit::where('id', $data['unit_id'])->update(['status' => 'occupied']);

        return back()->with('success', 'Vínculo adicionado.');
    }

    public function destroyLink(Person $person, PersonUnitLink $link): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($person->tenant_id === $tenant->id && $link->person_id === $person->id, 403);

        $link->update(['end_date' => now()->toDateString()]);

        // Verifica se a unidade ainda tem moradores ativos
        $hasResidents = PersonUnitLink::where('unit_id', $link->unit_id)->whereNull('end_date')->exists();
        if (! $hasResidents) {
            Unit::where('id', $link->unit_id)->update(['status' => 'vacant']);
        }

        return back()->with('success', 'Vínculo encerrado.');
    }

    public function searchByCpf(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $cpf = preg_replace('/\D/', '', $request->cpf ?? '');
        if (strlen($cpf) !== 11) {
            return response()->json(['person' => null]);
        }

        $person = Person::where('tenant_id', $tenant->id)
            ->where('cpf', 'like', "%{$cpf}%")
            ->first(['id', 'name', 'cpf', 'email', 'phone']);

        return response()->json(['person' => $person]);
    }

    private function validated(Request $request, string $tenantId, ?string $excludeId = null): array
    {
        $cpfRule = "nullable|string|max:14|unique:persons,cpf,{$excludeId},id,tenant_id,{$tenantId}";

        return $request->validate([
            'name' => 'required|string|max:150',
            'cpf' => $cpfRule,
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'zip_code' => 'nullable|string|max:9',
            'street' => 'nullable|string|max:200',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'notes' => 'nullable|string|max:1000',
        ]);
    }
}
