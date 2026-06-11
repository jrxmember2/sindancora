<?php

namespace App\Dashboard\Resolvers\General;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

class TotalUnitsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Unidades']);
        }

        $count = $this->unitBase($ctx)->count();

        return [
            'label' => 'Unidades',
            'value' => $count,
            'formatted' => number_format($count, 0, ',', '.'),
            'color' => 'indigo',
            'icon' => 'DoorClosed',
        ];
    }
}
