<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Resumo do dashboard para o app: reusa o dashboard modular do painel
 * (WidgetRegistry + resolvers), com o mesmo gating por permissão/módulo e
 * escopo de condomínio por papel.
 */
class DashboardController extends AppController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    #[OA\Get(
        path: '/v1/app/dashboard',
        operationId: 'appDashboard',
        summary: 'Dashboard do app: widgets visíveis (meta) e dados dos não-lazy',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [
            new OA\Response(response: 200, description: 'Widgets e dados', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $page = $this->dashboard->buildPage($request);

        // O app não usa preferências do painel web; entrega meta/dados/filtros/header.
        return $this->ok([
            'meta' => $page['meta'],
            'data' => $page['data'],
            'filters' => $page['filters'],
            'header' => $page['header'],
        ]);
    }

    #[OA\Get(
        path: '/v1/app/dashboard/widgets/{key}',
        operationId: 'appDashboardWidget',
        summary: 'Dados de um widget específico (lazy/refresh)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Payload do widget'),
            new OA\Response(response: 403, description: 'Widget não visível para o usuário'),
        ],
    )]
    public function widget(Request $request, string $key): JsonResponse
    {
        return $this->ok([
            'key' => $key,
            'data' => $this->dashboard->resolveWidget($request, $key),
        ]);
    }
}
