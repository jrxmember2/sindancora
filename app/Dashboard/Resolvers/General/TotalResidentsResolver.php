<?php

namespace App\Dashboard\Resolvers\General;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

class TotalResidentsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Moradores']);
        }

        $count = $this->residentLinkBase($ctx)->distinct('person_id')->count('person_id');

        return [
            'label' => 'Moradores vinculados',
            'value' => $count,
            'formatted' => number_format($count, 0, ',', '.'),
            'color' => 'violet',
            'icon' => 'Users',
        ];
    }
}
