<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CondominiumResource;
use App\Models\Condominium;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CondominiumController extends ApiController
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    #[OA\Get(
        path: '/v1/condominiums',
        summary: 'Lista condomínios do tenant',
        security: [['apiKey' => []]],
        tags: ['Condomínios'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Condominium::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name');

        return $this->paginated($query->paginate($this->perPage($request)), CondominiumResource::class);
    }

    #[OA\Get(
        path: '/v1/condominiums/{id}',
        summary: 'Detalha um condomínio',
        security: [['apiKey' => []]],
        tags: ['Condomínios'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK'), new OA\Response(response: 404, description: 'Não encontrado')],
    )]
    public function show(string $id): JsonResponse
    {
        return $this->item(new CondominiumResource(Condominium::findOrFail($id)));
    }

    #[OA\Post(
        path: '/v1/condominiums',
        summary: 'Cria um condomínio',
        security: [['apiKey' => []]],
        tags: ['Condomínios'],
        responses: [new OA\Response(response: 201, description: 'Criado'), new OA\Response(response: 402, description: 'Limite do plano')],
    )]
    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $this->planLimitService->check($tenant, 'condominiums');

        $data = $this->validateData($request);
        $condominium = Condominium::create($data + ['status' => $data['status'] ?? 'active']);

        $this->planLimitService->increment($tenant, 'condominiums');

        return $this->item(new CondominiumResource($condominium), 201);
    }

    #[OA\Put(
        path: '/v1/condominiums/{id}',
        summary: 'Atualiza um condomínio',
        security: [['apiKey' => []]],
        tags: ['Condomínios'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $condominium = Condominium::findOrFail($id);
        $condominium->update($this->validateData($request));

        return $this->item(new CondominiumResource($condominium));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'zip_code' => 'nullable|string|max:9',
            'street' => 'nullable|string|max:200',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'status' => 'nullable|in:active,inactive',
        ]);
    }
}
