<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UnitController extends ApiController
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    #[OA\Get(
        path: '/v1/units',
        summary: 'Lista unidades',
        security: [['apiKey' => []]],
        tags: ['Unidades'],
        parameters: [
            new OA\Parameter(name: 'condominium_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Unit::query()
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('number');

        return $this->paginated($query->paginate($this->perPage($request)), UnitResource::class);
    }

    #[OA\Get(
        path: '/v1/units/{id}',
        summary: 'Detalha uma unidade',
        security: [['apiKey' => []]],
        tags: ['Unidades'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function show(string $id): JsonResponse
    {
        return $this->item(new UnitResource(Unit::findOrFail($id)));
    }

    #[OA\Post(
        path: '/v1/units',
        summary: 'Cria uma unidade',
        security: [['apiKey' => []]],
        tags: ['Unidades'],
        responses: [new OA\Response(response: 201, description: 'Criada'), new OA\Response(response: 402, description: 'Limite do plano')],
    )]
    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $this->planLimitService->check($tenant, 'units');

        $unit = Unit::create($this->validateData($request));

        $this->planLimitService->increment($tenant, 'units');

        return $this->item(new UnitResource($unit), 201);
    }

    #[OA\Put(
        path: '/v1/units/{id}',
        summary: 'Atualiza uma unidade',
        security: [['apiKey' => []]],
        tags: ['Unidades'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $unit->update($this->validateData($request, $unit->id));

        return $this->item(new UnitResource($unit));
    }

    private function validateData(Request $request, ?string $excludeId = null): array
    {
        $tenantId = app('tenant')->id;

        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'block_id' => "nullable|uuid|exists:blocks,id,tenant_id,{$tenantId}",
            'number' => 'required|string|max:30',
            'floor' => 'nullable|string|max:20',
            'type' => 'nullable|string|max:30',
            'area_m2' => 'nullable|numeric|min:0',
            'fraction' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:occupied,vacant,under_renovation',
        ]);
    }
}
