<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Models\Condominium;
use App\Models\Parcel;
use App\Models\VisitorAuthorization;
use App\Models\VisitorVisit;
use App\Services\GatehouseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Portaria e encomendas para o app do síndico. Reusa GatehouseService
 * (check-in/out, validação de token QR) e as consultas do painel.
 */
class GatehouseController extends AppController
{
    use ScopesCondominiumsByRole;

    public function __construct(private readonly GatehouseService $service) {}

    #[OA\Get(
        path: '/v1/app/gatehouse/visits',
        operationId: 'appGatehouseVisits',
        summary: 'Visitantes: presentes e histórico paginado (filtro: condominium_id)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Presentes + histórico')],
    )]
    public function visits(Request $request): JsonResponse
    {
        $present = VisitorVisit::present()
            ->with(['condominium:id,name', 'unit:id,number'])
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->orderByDesc('check_in_at')
            ->get()
            ->map(fn (VisitorVisit $v) => $this->visitPayload($v));

        $log = VisitorVisit::with(['condominium:id,name', 'unit:id,number'])
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->orderByDesc('check_in_at')
            ->paginate(min((int) $request->query('per_page', 30), 50));

        return response()->json([
            'success' => true,
            'data' => [
                'present' => $present,
                'log' => $log->getCollection()->map(fn (VisitorVisit $v) => $this->visitPayload($v)),
            ],
            'meta' => [
                'current_page' => $log->currentPage(),
                'per_page' => $log->perPage(),
                'total' => $log->total(),
                'last_page' => $log->lastPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/v1/app/gatehouse/validate-token',
        operationId: 'appGatehouseValidate',
        summary: 'Validar QR/token de autorização de visitante',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Resultado da validação')],
    )]
    public function validateToken(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:32']);

        $auth = $this->service->findByToken($data['token']);

        if ($auth === null) {
            return $this->ok(['found' => false]);
        }

        return $this->ok([
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
        ]);
    }

    #[OA\Post(
        path: '/v1/app/gatehouse/check-in',
        operationId: 'appGatehouseCheckIn',
        summary: 'Registrar entrada: autorizado (authorization_id) ou avulso (dados do visitante)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 201, description: 'Entrada registrada')],
    )]
    public function checkIn(Request $request): JsonResponse
    {
        if ($request->filled('authorization_id')) {
            $data = $request->validate(['authorization_id' => 'required|uuid']);

            $auth = VisitorAuthorization::find($data['authorization_id']);
            abort_if($auth === null, 404);

            if (! $auth->isValid()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'AUTHORIZATION_INVALID', 'message' => 'Autorização inválida, expirada ou já utilizada.'],
                ], 422);
            }

            $visit = $this->service->checkInAuthorized($auth, $request->user());

            return $this->ok($this->visitPayload($visit->load(['condominium:id,name', 'unit:id,number'])), 201);
        }

        $data = $request->validate([
            'condominium_id' => 'required|uuid',
            'unit_id' => 'nullable|uuid',
            'visitor_name' => 'required|string|max:255',
            'visitor_document' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        abort_unless(Condominium::whereKey($data['condominium_id'])->exists(), 422);

        $visit = $this->service->checkInWalkIn($data, $request->user());

        return $this->ok($this->visitPayload($visit->load(['condominium:id,name', 'unit:id,number'])), 201);
    }

    #[OA\Post(
        path: '/v1/app/gatehouse/visits/{visit}/check-out',
        operationId: 'appGatehouseCheckOut',
        summary: 'Registrar saída do visitante',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'visit', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Saída registrada')],
    )]
    public function checkOut(VisitorVisit $visit): JsonResponse
    {
        $this->authorizeTenant($visit);
        $this->service->checkOut($visit);

        return $this->ok($this->visitPayload($visit->refresh()->load(['condominium:id,name', 'unit:id,number'])));
    }

    #[OA\Get(
        path: '/v1/app/parcels',
        operationId: 'appParcelsIndex',
        summary: 'Listar encomendas (filtros: condominium_id, status)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada')],
    )]
    public function parcels(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $condominiums = $this->accessibleCondominiums($tenant->id, $request->user());
        $condominiumIds = $condominiums->pluck('id')->all();
        $selected = $request->input('condominium_id');
        $selected = is_string($selected) && in_array($selected, $condominiumIds, true) ? $selected : null;
        $status = in_array($request->input('status'), array_keys(Parcel::STATUSES), true) ? $request->input('status') : null;

        $parcels = Parcel::with(['condominium:id,name', 'unit:id,number'])
            ->whereIn('condominium_id', $condominiumIds)
            ->when($selected, fn (Builder $q, string $id) => $q->where('condominium_id', $id))
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->orderByRaw("CASE WHEN status = 'awaiting' THEN 0 ELSE 1 END")
            ->orderByDesc('received_at')
            ->paginate(min((int) $request->query('per_page', 30), 50));

        return response()->json([
            'success' => true,
            'data' => $parcels->getCollection()->map(fn (Parcel $p) => $this->parcelPayload($p)),
            'meta' => [
                'current_page' => $parcels->currentPage(),
                'per_page' => $parcels->perPage(),
                'total' => $parcels->total(),
                'last_page' => $parcels->lastPage(),
            ],
            'options' => [
                'statuses' => Parcel::STATUSES,
                'condominiums' => $condominiums,
            ],
        ]);
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

    private function parcelPayload(Parcel $p): array
    {
        return [
            'id' => $p->id,
            'description' => $p->description,
            'carrier' => $p->carrier,
            'status' => $p->status,
            'status_label' => Parcel::STATUSES[$p->status] ?? $p->status,
            'condominium' => $p->condominium?->name,
            'unit' => $p->unit?->number,
            'received_at' => $p->received_at?->toIso8601String(),
            'picked_up_at' => $p->picked_up_at?->toIso8601String(),
        ];
    }
}
