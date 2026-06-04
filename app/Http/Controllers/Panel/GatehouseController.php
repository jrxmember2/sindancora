<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\VisitorAuthorization;
use App\Models\VisitorVisit;
use App\Services\GatehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoramento da Portaria pelo gestor: visitantes presentes, histórico de acessos
 * e gestão de autorizações (pré-autorizar/revogar). Telas do porteiro ficam em /portaria.
 */
class GatehouseController extends Controller
{
    public function __construct(private readonly GatehouseService $service) {}

    public function index(Request $request): Response
    {
        $present = VisitorVisit::present()
            ->with(['condominium:id,name', 'unit:id,number'])
            ->orderByDesc('check_in_at')
            ->get()
            ->map(fn ($v) => $this->visitPayload($v));

        $recent = VisitorVisit::with(['condominium:id,name', 'unit:id,number'])
            ->orderByDesc('check_in_at')
            ->limit(30)
            ->get()
            ->map(fn ($v) => $this->visitPayload($v));

        $authorizations = VisitorAuthorization::with(['condominium:id,name', 'unit:id,number'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'visitor_name' => $a->visitor_name,
                'visitor_document' => $a->visitor_document,
                'type' => $a->type,
                'type_label' => VisitorAuthorization::TYPES[$a->type] ?? $a->type,
                'status' => $a->status,
                'status_label' => VisitorAuthorization::STATUSES[$a->status] ?? $a->status,
                'token' => $a->token,
                'condominium' => $a->condominium?->name,
                'unit' => $a->unit?->number,
                'valid_from' => $a->valid_from?->toDateString(),
                'valid_until' => $a->valid_until?->toDateString(),
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Panel/Gatehouse/Index', [
            'present' => $present,
            'recent' => $recent,
            'authorizations' => $authorizations,
            'condominiums' => $this->condominiumOptions(),
            'canManage' => $request->user()?->hasPermission('gatehouse:manage') ?? false,
        ]);
    }

    public function storeAuthorization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'condominium_id' => 'required|uuid',
            'unit_id' => 'required|uuid',
            'visitor_name' => 'required|string|max:255',
            'visitor_document' => 'nullable|string|max:50',
            'visitor_phone' => 'nullable|string|max:30',
            'type' => 'required|in:single,recurring',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'notes' => 'nullable|string|max:500',
        ]);

        abort_unless(Condominium::whereKey($data['condominium_id'])->exists(), 422);

        $this->service->authorize($data, $request->user());

        return back()->with('success', 'Autorização criada.');
    }

    public function revokeAuthorization(VisitorAuthorization $authorization): RedirectResponse
    {
        $this->service->revoke($authorization);

        return back()->with('success', 'Autorização revogada.');
    }

    private function visitPayload(VisitorVisit $v): array
    {
        return [
            'id' => $v->id,
            'visitor_name' => $v->visitor_name,
            'visitor_document' => $v->visitor_document,
            'condominium' => $v->condominium?->name,
            'unit' => $v->unit?->number,
            'check_in_at' => $v->check_in_at?->toIso8601String(),
            'check_out_at' => $v->check_out_at?->toIso8601String(),
        ];
    }

    private function condominiumOptions(): array
    {
        return Condominium::orderBy('name')
            ->with(['units:id,number,condominium_id'])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'units' => $c->units->map(fn ($u) => ['id' => $u->id, 'number' => $u->number]),
            ])
            ->all();
    }
}
