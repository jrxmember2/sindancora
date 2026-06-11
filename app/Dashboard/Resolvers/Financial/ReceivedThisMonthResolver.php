<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Support\Money;

/**
 * Valor recebido (cobranças pagas) no mês corrente, com variação contra o mês
 * anterior e sparkline dos últimos 6 meses.
 */
class ReceivedThisMonthResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Recebido no mês']);
        }

        $startThis = now()->startOfMonth();
        $startPrev = now()->subMonthNoOverflow()->startOfMonth();

        $thisMonth = (float) $this->chargeBase($ctx)->where('status', 'paid')
            ->where('paid_at', '>=', $startThis)
            ->sum('paid_amount');

        $prevMonth = (float) $this->chargeBase($ctx)->where('status', 'paid')
            ->whereBetween('paid_at', [$startPrev, $startThis])
            ->sum('paid_amount');

        $delta = $prevMonth > 0
            ? round((($thisMonth - $prevMonth) / $prevMonth) * 100, 1)
            : ($thisMonth > 0 ? 100.0 : 0.0);

        $sparkline = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonthsNoOverflow($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $sparkline[] = round((float) $this->chargeBase($ctx)->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end])
                ->sum('paid_amount'), 2);
        }

        return [
            'label' => 'Recebido no mês',
            'value' => $thisMonth,
            'formatted' => Money::brl($thisMonth),
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'sparkline' => $sparkline,
            'color' => 'emerald',
            'icon' => 'CircleDollarSign',
            'caption' => 'vs. mês anterior',
        ];
    }
}
