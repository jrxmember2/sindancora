<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\AssemblyAgendaItem;
use App\Models\Condominium;
use App\Services\AssemblyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssemblyController extends Controller
{
    public function __construct(private readonly AssemblyService $service) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $assemblies = Assembly::where('tenant_id', $tenant->id)
            ->with('condominium:id,name')
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Assemblies/Index', [
            'assemblies' => $assemblies,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'statuses' => Assembly::STATUSES,
            'filters' => $request->only(['condominium_id', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Assemblies/Create', [
            'condominiums' => $this->condominiumOptions(app('tenant')->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id);

        $assembly = Assembly::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]));

        return redirect()->route('assemblies.show', $assembly)->with('success', 'Assembleia criada. Adicione os itens da pauta.');
    }

    public function show(Assembly $assembly): Response
    {
        $assembly = $this->authorizeTenant($assembly);
        $assembly->load(['condominium:id,name', 'items.options']);

        return Inertia::render('Assemblies/Show', [
            'assembly' => array_merge($assembly->toArray(), [
                'has_minutes' => filled($assembly->minutes),
            ]),
            'results' => $this->service->results($assembly),
            'statuses' => Assembly::STATUSES,
        ]);
    }

    public function edit(Assembly $assembly): Response
    {
        $assembly = $this->authorizeTenant($assembly);

        return Inertia::render('Assemblies/Edit', [
            'assembly' => $assembly,
            'condominiums' => $this->condominiumOptions($assembly->tenant_id),
        ]);
    }

    public function update(Request $request, Assembly $assembly): RedirectResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        $assembly->update($this->validated($request, $assembly->tenant_id));

        return redirect()->route('assemblies.show', $assembly)->with('success', 'Assembleia atualizada.');
    }

    public function destroy(Assembly $assembly): RedirectResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        $assembly->delete();

        return redirect()->route('assemblies.index')->with('success', 'Assembleia removida.');
    }

    public function storeItem(Request $request, Assembly $assembly): RedirectResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        abort_if($assembly->status !== 'draft', 422, 'A pauta só pode ser editada com a assembleia em rascunho.');

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:120',
        ]);

        $item = $assembly->items()->create([
            'tenant_id' => $assembly->tenant_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'position' => (int) $assembly->items()->max('position') + 1,
        ]);

        foreach (array_values($data['options']) as $i => $label) {
            $item->options()->create(['label' => $label, 'position' => $i]);
        }

        return back()->with('success', 'Item da pauta adicionado.');
    }

    public function destroyItem(Assembly $assembly, AssemblyAgendaItem $item): RedirectResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        abort_if($assembly->status !== 'draft', 422, 'A pauta só pode ser editada com a assembleia em rascunho.');
        abort_unless($item->assembly_id === $assembly->id, 404);

        $item->delete();

        return back()->with('success', 'Item removido.');
    }

    public function open(Assembly $assembly): RedirectResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        abort_if($assembly->items()->count() === 0, 422, 'Adicione ao menos um item à pauta antes de abrir a votação.');

        $assembly->update(['status' => 'open']);

        return back()->with('success', 'Votação aberta.');
    }

    public function close(Assembly $assembly): RedirectResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        $assembly->update(['status' => 'closed']);

        return back()->with('success', 'Votação encerrada.');
    }

    public function generateMinutes(Assembly $assembly): RedirectResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        $this->service->generateMinutes($assembly);

        return back()->with('success', 'Ata gerada.');
    }

    public function downloadMinutes(Assembly $assembly): HttpResponse
    {
        $assembly = $this->authorizeTenant($assembly);
        abort_unless(filled($assembly->minutes), 404);
        $assembly->loadMissing('condominium:id,name');

        $pdf = Pdf::loadView('assemblies.minutes', ['assembly' => $assembly]);

        return $pdf->download('ata-'.\Illuminate\Support\Str::slug($assembly->title).'.pdf');
    }

    private function validated(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'scheduled_at' => 'nullable|date',
        ]);
    }

    private function authorizeTenant(Assembly $assembly): Assembly
    {
        abort_unless($assembly->tenant_id === app('tenant')->id, 403);

        return $assembly;
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }
}
