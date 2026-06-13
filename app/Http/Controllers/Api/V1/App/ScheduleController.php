<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Services\ScheduleEventBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Cronograma consolidado para o app (agenda do síndico). Mesmo payload do
 * painel, montado pelo ScheduleEventBuilder compartilhado.
 */
class ScheduleController extends AppController
{
    public function __construct(private readonly ScheduleEventBuilder $builder) {}

    #[OA\Get(
        path: '/v1/app/schedule',
        operationId: 'appSchedule',
        summary: 'Cronograma consolidado do mês (filtros: month=YYYY-MM, condominium_id, source)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Calendário, eventos e resumo')],
    )]
    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->builder->build($request));
    }
}
