<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ChargeResource;
use App\Models\Charge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChargeController extends ApiController
{
    #[OA\Get(
        path: '/v1/charges',
        summary: 'Lista cobranças',
        security: [['apiKey' => []]],
        tags: ['Cobranças'],
        parameters: [
            new OA\Parameter(name: 'condominium_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'unit_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'reference_month', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Charge::query()
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->unit_id, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->reference_month, fn ($q, $m) => $q->where('reference_month', $m))
            ->orderByDesc('due_date');

        return $this->paginated($query->paginate($this->perPage($request)), ChargeResource::class);
    }

    #[OA\Get(
        path: '/v1/charges/{id}',
        summary: 'Detalha uma cobrança',
        security: [['apiKey' => []]],
        tags: ['Cobranças'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function show(string $id): JsonResponse
    {
        return $this->item(new ChargeResource(Charge::findOrFail($id)));
    }

    #[OA\Post(
        path: '/v1/charges',
        summary: 'Cria uma cobrança',
        security: [['apiKey' => []]],
        tags: ['Cobranças'],
        responses: [new OA\Response(response: 201, description: 'Criada'), new OA\Response(response: 422, description: 'Validação')],
    )]
    public function store(Request $request): JsonResponse
    {
        $tenantId = app('tenant')->id;

        $data = $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'unit_id' => "required|uuid|exists:units,id,tenant_id,{$tenantId}",
            'person_id' => "nullable|uuid|exists:persons,id,tenant_id,{$tenantId}",
            'type' => 'required|in:'.implode(',', array_keys(Charge::TYPES)),
            'description' => 'required|string|max:200',
            'reference_month' => 'nullable|date_format:Y-m',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'fine_rate' => 'nullable|numeric|min:0|max:100',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $charge = Charge::create($data + ['status' => 'pending']);

        return $this->item(new ChargeResource($charge), 201);
    }
}
