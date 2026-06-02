<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Assembly;
use App\Models\AssemblyAgendaItem;
use App\Models\AssemblyAttendance;
use App\Models\AssemblyVote;
use App\Services\AssemblyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssemblyController extends Controller
{
    use InteractsWithResident;

    public function __construct(private readonly AssemblyService $service) {}

    public function index(): Response
    {
        $condominiumIds = $this->condominiumIds() ?: ['-'];

        $assemblies = Assembly::whereIn('condominium_id', $condominiumIds)
            ->whereIn('status', ['open', 'closed'])
            ->with('condominium:id,name')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'status' => $a->status,
                'scheduled_at' => $a->scheduled_at?->toIso8601String(),
                'condominium' => $a->condominium ? ['name' => $a->condominium->name] : null,
            ]);

        return Inertia::render('Portal/Assemblies/Index', ['assemblies' => $assemblies]);
    }

    public function show(Assembly $assembly): Response
    {
        $this->authorizeAccess($assembly);
        $assembly->load(['condominium:id,name', 'items.options']);

        $unitIds = $this->unitIds();

        // Voto do morador por item (pela primeira unidade vinculada).
        $myVotes = AssemblyVote::where('assembly_id', $assembly->id)
            ->whereIn('unit_id', $unitIds ?: ['-'])
            ->pluck('option_id', 'agenda_item_id');

        $present = AssemblyAttendance::where('assembly_id', $assembly->id)
            ->whereIn('unit_id', $unitIds ?: ['-'])
            ->exists();

        return Inertia::render('Portal/Assemblies/Show', [
            'assembly' => [
                'id' => $assembly->id,
                'title' => $assembly->title,
                'description' => $assembly->description,
                'status' => $assembly->status,
                'scheduled_at' => $assembly->scheduled_at?->toIso8601String(),
                'minutes' => $assembly->minutes,
                'condominium' => $assembly->condominium ? ['name' => $assembly->condominium->name] : null,
                'items' => $assembly->items->map(fn ($it) => [
                    'id' => $it->id,
                    'title' => $it->title,
                    'description' => $it->description,
                    'options' => $it->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label]),
                ]),
            ],
            'myVotes' => $myVotes,
            'present' => $present,
            'unitCount' => count($unitIds),
        ]);
    }

    public function attend(Assembly $assembly): RedirectResponse
    {
        $this->authorizeAccess($assembly);
        $this->service->registerAttendance($assembly, $this->unitIds(), $this->resident()->id);

        return back()->with('success', 'Presença registrada.');
    }

    public function vote(Request $request, Assembly $assembly, AssemblyAgendaItem $item): RedirectResponse
    {
        $this->authorizeAccess($assembly);
        abort_unless($item->assembly_id === $assembly->id, 404);

        $data = $request->validate(['option_id' => 'required|uuid']);

        $this->service->castVote($assembly, $item, $data['option_id'], $this->unitIds(), $this->resident()->id);

        return back()->with('success', 'Voto registrado.');
    }

    /** A assembleia precisa ser de um condomínio do morador. */
    private function authorizeAccess(Assembly $assembly): void
    {
        abort_unless($assembly->tenant_id === app('tenant')->id, 403);
        abort_unless(in_array($assembly->condominium_id, $this->condominiumIds(), true), 403);
    }
}
