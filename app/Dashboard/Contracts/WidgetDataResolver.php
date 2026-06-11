<?php

namespace App\Dashboard\Contracts;

use App\Dashboard\DashboardContext;

/**
 * Contrato dos resolvers de dados de widget. Cada widget tem um resolver que
 * recebe o contexto (tenant, usuário, escopo, filtros) e devolve o payload já
 * formatado para o tipo visual correspondente.
 *
 * O formato do array retornado depende do WidgetType do widget — ver
 * docs/tecnico/dashboard-modular.md.
 */
interface WidgetDataResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(DashboardContext $context): array;
}
