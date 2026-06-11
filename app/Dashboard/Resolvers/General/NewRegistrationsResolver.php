<?php

namespace App\Dashboard\Resolvers\General;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use Carbon\Carbon;

/**
 * Novas unidades cadastradas no mês corrente, com variação contra o mês anterior
 * e mini série (sparkline) dos últimos 6 meses.
 */
class NewRegistrationsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Novos cadastros']);
        }

        $startThis = now()->startOfMonth();
        $startPrev = now()->subMonthNoOverflow()->startOfMonth();

        $thisMonth = $this->unitBase($ctx)->where('created_at', '>=', $startThis)->count();
        $prevMonth = $this->unitBase($ctx)
            ->whereBetween('created_at', [$startPrev, $startThis])
            ->count();

        $delta = $prevMonth > 0
            ? round((($thisMonth - $prevMonth) / $prevMonth) * 100, 1)
            : ($thisMonth > 0 ? 100.0 : 0.0);

        $sparkline = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonthsNoOverflow($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $sparkline[] = $this->unitBase($ctx)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }

        return [
            'label' => 'Novas unidades no mês',
            'value' => $thisMonth,
            'formatted' => number_format($thisMonth, 0, ',', '.'),
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'sparkline' => $sparkline,
            'color' => 'emerald',
            'icon' => 'TrendingUp',
            'caption' => 'vs. mês anterior',
        ];
    }
}
