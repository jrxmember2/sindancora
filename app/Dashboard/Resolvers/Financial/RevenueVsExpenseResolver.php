<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

/**
 * Receitas (cobranças pagas) x Despesas (contas pagas) nos últimos 12 meses,
 * por mês de pagamento. Gráfico de barras agrupadas. Widget pesado (lazy).
 */
class RevenueVsExpenseResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty();
        }

        $categories = [];
        $revenue = [];
        $expense = [];

        for ($i = 11; $i >= 0; $i--) {
            $start = now()->subMonthsNoOverflow($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            $revenue[] = round((float) $this->chargeBase($ctx)->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end])->sum('paid_amount'), 2);

            $expense[] = round((float) $this->expenseBase($ctx)->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end])->sum('paid_amount'), 2);

            $categories[] = $start->translatedFormat('M/y');
        }

        if (array_sum($revenue) === 0.0 && array_sum($expense) === 0.0) {
            return $this->empty();
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Receitas', 'data' => $revenue],
                ['name' => 'Despesas', 'data' => $expense],
            ],
            'format' => 'currency',
            'colors' => ['emerald', 'red'],
        ];
    }
}
