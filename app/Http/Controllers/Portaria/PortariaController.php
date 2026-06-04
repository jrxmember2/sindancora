<?php

namespace App\Http\Controllers\Portaria;

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
 * Área dedicada da portaria (porteiro). Validar QR/token, registrar entradas/saídas
 * e acompanhar visitantes presentes. Escopo por tenant (todos os condomínios do tenant).
 */
class PortariaController extends Controller
{
    public function __construct(private readonly GatehouseService $service) {}

    public function index(): Response
    {
        $present = VisitorVisit::present()
            ->with(['condominium:id,name', 'unit:id,number'])
            ->orderByDesc('check_in_at')
            ->get()
            ->map(fn ($v) => $this->visitPayload($v));

        return Inertia::render('Portaria/Index', [
            'present' => $present,
            'condominiums' => $this->condominiumOptions(),
        ]);
    }

    public function validateForm(): Response
    {
        return Inertia::render('Portaria/Validate', ['result' => null, 'token' => '']);
    }

    public function validateToken(Request $request): Response
    {
        $data = $request->validate(['token' => 'required|string|max:32']);

        $auth = $this->service->findByToken($data['token']);

        $result = $auth === null
            ? ['found' => false]
            : [
                'found' => true,
                'id' => $auth->id,
                'visitor_name' => $auth->visitor_name,
                'visitor_document' => $auth->visitor_document,
                'type' => $auth->type,
                'type_label' => VisitorAuthorization::TYPES[$auth->type] ?? $auth->type,
                'status' => $auth->status,
                'status_label' => VisitorAuthorization::STATUSES[$auth->status] ?? $auth->status,
                'valid' => $auth->isValid(),
                'condominium' => $auth->condominium?->name,
                'unit' => $auth->unit?->number,
                'valid_from' => $auth->valid_from?->toDateString(),
                'valid_until' => $auth->valid_until?->toDateString(),
            ];

        return Inertia::render('Portaria/Validate', [
            'result' => $result,
            'token' => $data['token'],
        ]);
    }

    /** Registra entrada de visitante pré-autorizado (a partir do token validado). */
    public function checkInAuthorized(Request $request): RedirectResponse
    {
        $data = $request->validate(['authorization_id' => 'required|uuid']);

        $auth = VisitorAuthorization::find($data['authorization_id']);
        abort_if($auth === null, 404);

        if (! $auth->isValid()) {
            return back()->with('error', 'Autorização inválida, expirada ou já utilizada.');
        }

        $this->service->checkInAuthorized($auth, $request->user());

        return redirect()->route('portaria.index')->with('success', "Entrada registrada: {$auth->visitor_name}.");
    }

    /** Registra entrada de visitante avulso (walk-in). */
    public function checkInWalkIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'condominium_id' => 'required|uuid',
            'unit_id' => 'nullable|uuid',
            'visitor_name' => 'required|string|max:255',
            'visitor_document' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        // Garante que o condomínio é do tenant atual.
        abort_unless(Condominium::whereKey($data['condominium_id'])->exists(), 422);

        $this->service->checkInWalkIn($data, $request->user());

        return redirect()->route('portaria.index')->with('success', "Entrada registrada: {$data['visitor_name']}.");
    }

    public function checkOut(VisitorVisit $visit): RedirectResponse
    {
        $this->service->checkOut($visit);

        return back()->with('success', "Saída registrada: {$visit->visitor_name}.");
    }

    public function log(Request $request): Response
    {
        $visits = VisitorVisit::with(['condominium:id,name', 'unit:id,number'])
            ->orderByDesc('check_in_at')
            ->paginate(30)
            ->through(fn ($v) => $this->visitPayload($v));

        return Inertia::render('Portaria/Log', ['visits' => $visits]);
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

    /** Condomínios do tenant com suas unidades, para o formulário de walk-in. */
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
