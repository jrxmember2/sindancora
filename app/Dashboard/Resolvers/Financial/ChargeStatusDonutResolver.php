<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Models\Charge;

/**
 * Distribuição das cobranças por status (snapshot atual). Donut.
 */
class ChargeStatusDonutResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty();
        }

        $counts = $this->chargeBase($ctx)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $palette = [
            'pending' => 'amber',
            'paid' => 'emerald',
            'overdue' => 'red',
            'cancelled' => 'gray',
        ];

        $labels = [];
        $series = [];
        $colors = [];

        foreach (Charge::STATUSES as $status => $label) {
            $value = (int) ($counts[$status] ?? 0);
            if ($value === 0) {
                continue;
            }
            $labels[] = $label;
            $series[] = $value;
            $colors[] = $palette[$status] ?? 'blue';
        }

        if ($series === []) {
            return $this->empty();
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
            'total' => array_sum($series),
            'totalLabel' => 'Cobranças',
        ];
    }
}
