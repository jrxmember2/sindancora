<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'API REST do SindÂncora — SaaS de Gestão Condominial',
    title: 'SindÂncora API',
    contact: new OA\Contact(email: 'suporte@sindancora.com.br'),
    license: new OA\License(name: 'Proprietário'),
)]
#[OA\Server(url: '/api', description: 'API Base')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Schema(
    schema: 'SuccessResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(
            property: 'error',
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'message', type: 'string'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\SecurityScheme(
    securityScheme: 'apiKey',
    type: 'http',
    scheme: 'bearer',
    description: 'API Key do tenant no formato sk_live_... enviada como Bearer token.',
)]
#[OA\Tag(name: 'Auth', description: 'Autenticação')]
#[OA\Tag(name: 'Tenant', description: 'Dados do tenant autenticado')]
#[OA\Tag(name: 'Condomínios', description: 'Condomínios do tenant')]
#[OA\Tag(name: 'Unidades', description: 'Unidades dos condomínios')]
#[OA\Tag(name: 'Pessoas', description: 'Pessoas do tenant')]
#[OA\Tag(name: 'Cobranças', description: 'Cobranças financeiras')]
abstract class ApiController extends Controller
{
    /** Resposta de sucesso no envelope padrão {success, data}. */
    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    /** Coleção paginada no envelope padrão com meta de paginação. */
    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $resourceClass::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** Item único shaped por um JsonResource. */
    protected function item(JsonResource $resource, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $resource], $status);
    }

    /** Tamanho de página normalizado (1..100, default 20). */
    protected function perPage(\Illuminate\Http\Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 100);
    }
}
