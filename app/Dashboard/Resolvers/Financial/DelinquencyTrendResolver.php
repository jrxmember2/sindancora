<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

/**
 * Evolução da inadimplência nos últimos 12 meses: valor (principal) das cobranças
 * ainda em aberto agrupado pelo mês de vencimento. Widget pesado (lazy).
 */
class DelinquencyTrendResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty();
        }

        $categories = [];
        $data = [];

        for ($i = 11; $i >= 0; $i--) {
            $start = now()->subMonthsNoOverflow($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            $total = (float) $this->chargeBase($ctx)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            $categories[] = $start->translatedFormat('M/y');
            $data[] = round($total, 2);
        }

        if (array_sum($data) === 0.0) {
            return $this->empty();
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Em aberto', 'data' => $data],
            ],
            'format' => 'currency',
            'color' => 'red',
        ];
    }
}
