<?php

namespace App\Dashboard\Resolvers\Operations;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

/**
 * Obras/reformas em andamento e quantas estão com prazo estourado. KPI.
 */
class ActiveWorksResolver extends BaseResolver
{
    private const ACTIVE = ['planned', 'budgeting', 'approved', 'in_progress', 'paused'];

    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Obras em andamento']);
        }

        $active = $this->workBase($ctx)->whereIn('status', self::ACTIVE)->count();

        $overdue = $this->workBase($ctx)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('expected_end_date')
            ->whereDate('expected_end_date', '<', today()->toDateString())
            ->count();

        return [
            'label' => 'Obras em andamento',
            'value' => $active,
            'formatted' => number_format($active, 0, ',', '.'),
            'caption' => $overdue > 0 ? $overdue.' com prazo estourado' : 'Prazos em dia',
            'color' => $overdue > 0 ? 'amber' : 'blue',
            'icon' => 'Hammer',
        ];
    }
}
