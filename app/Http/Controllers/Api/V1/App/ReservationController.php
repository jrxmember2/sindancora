<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Reservas de áreas comuns para o app do síndico. Aprovar/rejeitar/cancelar
 * SEMPRE via ReservationService (transação + notificação + webhook).
 */
class ReservationController extends AppController
{
    public function __construct(private readonly ReservationService $service) {}

    #[OA\Get(
        path: '/v1/app/reservations',
        operationId: 'appReservationsIndex',
        summary: 'Listar reservas (filtros: status, common_area_id, condominium_id)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada')],
    )]
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->tenant();

        $reservations = Reservation::where('tenant_id', $tenant->id)
            ->with(['commonArea:id,name', 'condominium:id,name', 'requester:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->common_area_id, fn ($q, $id) => $q->where('common_area_id', $id))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->orderByDesc('date')
            ->orderBy('start_time')
            ->paginate(min((int) $request->query('per_page', 20), 50));

        return response()->json([
            'success' => true,
            'data' => $reservations->getCollection()->map(fn (Reservation $r) => $this->payload($r)),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
                'last_page' => $reservations->lastPage(),
            ],
            'options' => [
                'statuses' => Reservation::STATUSES,
                'areas' => CommonArea::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'condominium_id', 'requires_approval']),
            ],
        ]);
    }

    #[OA\Get(
        path: '/v1/app/reservations/{id}',
        operationId: 'appReservationsShow',
        summary: 'Detalhe da reserva',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Reserva')],
    )]
    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorizeTenant($reservation);
        $reservation->load(['commonArea:id,name,requires_approval', 'condominium:id,name', 'requester:id,name']);

        return $this->ok($this->payload($reservation, full: true));
    }

    #[OA\Post(
        path: '/v1/app/reservations/{id}/approve',
        operationId: 'appReservationsApprove',
        summary: 'Aprovar reserva pendente',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Reserva aprovada')],
    )]
    public function approve(Reservation $reservation): JsonResponse
    {
        $this->authorizeTenant($reservation);
        $this->service->approve($reservation);

        return $this->ok($this->payload($reservation->refresh()));
    }

    #[OA\Post(
        path: '/v1/app/reservations/{id}/reject',
        operationId: 'appReservationsReject',
        summary: 'Rejeitar reserva pendente (reason opcional)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Reserva rejeitada')],
    )]
    public function reject(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeTenant($reservation);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->service->reject($reservation, $data['reason'] ?? null);

        return $this->ok($this->payload($reservation->refresh()));
    }

    #[OA\Post(
        path: '/v1/app/reservations/{id}/cancel',
        operationId: 'appReservationsCancel',
        summary: 'Cancelar reserva (reason opcional)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Reserva cancelada')],
    )]
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeTenant($reservation);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->service->cancel($reservation, $data['reason'] ?? null);

        return $this->ok($this->payload($reservation->refresh()));
    }

    private function payload(Reservation $r, bool $full = false): array
    {
        $base = [
            'id' => $r->id,
            'date' => $r->date?->toDateString(),
            'start_time' => $r->start_time ? substr((string) $r->start_time, 0, 5) : null,
            'end_time' => $r->end_time ? substr((string) $r->end_time, 0, 5) : null,
            'status' => $r->status,
            'status_label' => Reservation::STATUSES[$r->status] ?? $r->status,
            'common_area' => $r->commonArea ? ['id' => $r->commonArea->id, 'name' => $r->commonArea->name] : null,
            'condominium' => $r->condominium ? ['id' => $r->condominium->id, 'name' => $r->condominium->name] : null,
            'requester' => $r->requester ? ['id' => $r->requester->id, 'name' => $r->requester->name] : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];

        if ($full) {
            $base['notes'] = $r->notes;
            $base['decision_reason'] = $r->decision_reason;
            $base['decided_at'] = $r->decided_at?->toIso8601String();
        }

        return $base;
    }
}
