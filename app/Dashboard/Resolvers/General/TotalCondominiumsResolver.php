<?php

namespace App\Dashboard\Resolvers\General;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

class TotalCondominiumsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        $count = count($ctx->scopeIds());

        return [
            'label' => 'Condomínios',
            'value' => $count,
            'formatted' => number_format($count, 0, ',', '.'),
            'color' => 'blue',
            'icon' => 'Building2',
        ];
    }
}
