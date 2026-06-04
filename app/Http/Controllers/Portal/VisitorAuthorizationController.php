<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Unit;
use App\Models\VisitorAuthorization;
use App\Services\GatehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pré-autorização de visitantes pelo morador. Gera um token apresentado como QR Code
 * (renderizado no front) que o porteiro valida na portaria.
 */
class VisitorAuthorizationController extends Controller
{
    use InteractsWithResident;

    public function __construct(private readonly GatehouseService $service) {}

    public function index(): Response
    {
        $authorizations = VisitorAuthorization::whereIn('unit_id', $this->unitIds() ?: ['-'])
            ->with(['unit:id,number', 'condominium:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'visitor_name' => $a->visitor_name,
                'type_label' => VisitorAuthorization::TYPES[$a->type] ?? $a->type,
                'status' => $a->status,
                'status_label' => VisitorAuthorization::STATUSES[$a->status] ?? $a->status,
                'condominium' => $a->condominium?->name,
                'unit' => $a->unit?->number,
                'valid_until' => $a->valid_until?->toDateString(),
            ]);

        return Inertia::render('Portal/Visitors/Index', ['authorizations' => $authorizations]);
    }

    public function create(): Response
    {
        $units = $this->activeLinks()->map(fn ($l) => [
            'id' => $l->unit->id,
            'label' => trim(($l->unit->block?->name ? $l->unit->block->name.' · ' : '').'Un. '.$l->unit->number.' — '.$l->unit->condominium?->name),
        ]);

        return Inertia::render('Portal/Visitors/Create', ['units' => $units]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'unit_id' => 'required|uuid',
            'visitor_name' => 'required|string|max:255',
            'visitor_document' => 'nullable|string|max:50',
            'visitor_phone' => 'nullable|string|max:30',
            'type' => 'required|in:single,recurring',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'notes' => 'nullable|string|max:500',
        ]);

        // A unidade precisa ser do morador.
        abort_unless(in_array($data['unit_id'], $this->unitIds(), true), 403);

        $unit = Unit::findOrFail($data['unit_id']);
        $data['condominium_id'] = $unit->condominium_id;

        $authorization = $this->service->authorize($data, $request->user());

        return redirect()->route('portal.visitors.show', $authorization->id)
            ->with('success', 'Visitante autorizado. Mostre o QR Code na portaria.');
    }

    public function show(VisitorAuthorization $authorization): Response
    {
        $this->authorizeAccess($authorization);
        $authorization->load(['unit:id,number', 'condominium:id,name']);

        return Inertia::render('Portal/Visitors/Show', [
            'authorization' => [
                'id' => $authorization->id,
                'visitor_name' => $authorization->visitor_name,
                'visitor_document' => $authorization->visitor_document,
                'visitor_phone' => $authorization->visitor_phone,
                'type_label' => VisitorAuthorization::TYPES[$authorization->type] ?? $authorization->type,
                'status' => $authorization->status,
                'status_label' => VisitorAuthorization::STATUSES[$authorization->status] ?? $authorization->status,
                'token' => $authorization->token,
                'condominium' => $authorization->condominium?->name,
                'unit' => $authorization->unit?->number,
                'valid_from' => $authorization->valid_from?->toDateString(),
                'valid_until' => $authorization->valid_until?->toDateString(),
                'notes' => $authorization->notes,
            ],
        ]);
    }

    public function revoke(VisitorAuthorization $authorization): RedirectResponse
    {
        $this->authorizeAccess($authorization);
        $this->service->revoke($authorization);

        return redirect()->route('portal.visitors.index')->with('success', 'Autorização revogada.');
    }

    /** A autorização precisa ser de uma unidade do morador. */
    private function authorizeAccess(VisitorAuthorization $authorization): void
    {
        abort_unless(in_array($authorization->unit_id, $this->unitIds(), true), 403);
    }
}
