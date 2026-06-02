<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Occurrence;
use App\Models\Unit;
use App\Services\OccurrenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OccurrenceController extends Controller
{
    use InteractsWithResident;

    public function __construct(private readonly OccurrenceService $service) {}

    public function index(): Response
    {
        $occurrences = Occurrence::where('created_by', Auth::id())
            ->with(['condominium:id,name', 'unit:id,number'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Portal/Occurrences/Index', [
            'occurrences' => $occurrences,
            'categories' => Occurrence::CATEGORIES,
            'priorities' => Occurrence::PRIORITIES,
            'statuses' => Occurrence::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Portal/Occurrences/Create', [
            'units' => $this->unitOptions(),
            'categories' => Occurrence::CATEGORIES,
            'priorities' => Occurrence::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $unitIds = $this->unitIds();

        $data = $request->validate([
            'unit_id' => ['required', 'uuid', 'in:'.implode(',', $unitIds ?: ['-'])],
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:'.implode(',', array_keys(Occurrence::CATEGORIES)),
            'priority' => 'required|in:'.implode(',', array_keys(Occurrence::PRIORITIES)),
        ]);

        $unit = Unit::findOrFail($data['unit_id']);

        $occurrence = Occurrence::create([
            'tenant_id' => app('tenant')->id,
            'condominium_id' => $unit->condominium_id,
            'unit_id' => $unit->id,
            'created_by' => Auth::id(),
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
        ]);

        $this->service->notifyNew($occurrence);

        return redirect()->route('portal.occurrences.show', $occurrence)
            ->with('success', 'Ocorrência registrada. Você será avisado a cada atualização.');
    }

    public function show(Occurrence $occurrence): Response
    {
        $this->authorizeOwner($occurrence);
        $occurrence->load(['condominium:id,name', 'unit:id,number', 'assignee:id,name', 'comments' => fn ($q) => $q->where('type', 'comment')->with('user:id,name')]);

        return Inertia::render('Portal/Occurrences/Show', [
            'occurrence' => $occurrence,
            'categories' => Occurrence::CATEGORIES,
            'priorities' => Occurrence::PRIORITIES,
            'statuses' => Occurrence::STATUSES,
        ]);
    }

    public function addComment(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorizeOwner($occurrence);
        $data = $request->validate(['body' => 'required|string|max:2000']);

        $this->service->addComment($occurrence, $data['body']);

        return back()->with('success', 'Comentário enviado.');
    }

    /** A ocorrência precisa ser do tenant e ter sido aberta pelo próprio morador. */
    private function authorizeOwner(Occurrence $occurrence): void
    {
        abort_unless($occurrence->tenant_id === app('tenant')->id, 403);
        abort_unless($occurrence->created_by === Auth::id(), 403);
    }

    private function unitOptions(): \Illuminate\Support\Collection
    {
        return $this->activeLinks()->map(fn ($l) => [
            'value' => $l->unit->id,
            'label' => trim(($l->unit->condominium?->name ?? '').' · '.($l->unit->block ? $l->unit->block->name.' · ' : '').$l->unit->number),
        ])->values();
    }
}
