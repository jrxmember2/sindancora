<?php

namespace App\Dashboard\Resolvers\Occurrences;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Models\Occurrence;

/**
 * Distribuição das ocorrências por status (snapshot atual). Donut.
 */
class OccurrenceStatusDonutResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty();
        }

        $counts = $this->occurrenceBase($ctx)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $palette = [
            'open' => 'blue',
            'in_progress' => 'amber',
            'closed' => 'emerald',
        ];

        $labels = [];
        $series = [];
        $colors = [];

        foreach (Occurrence::STATUSES as $status => $label) {
            $value = (int) ($counts[$status] ?? 0);
            if ($value === 0) {
                continue;
            }
            $labels[] = $label;
            $series[] = $value;
            $colors[] = $palette[$status] ?? 'gray';
        }

        if ($series === []) {
            return $this->empty();
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
            'total' => array_sum($series),
            'totalLabel' => 'Ocorrências',
        ];
    }
}
