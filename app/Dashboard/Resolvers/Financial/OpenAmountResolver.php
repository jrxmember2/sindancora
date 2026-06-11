<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Support\Money;

class OpenAmountResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Em aberto']);
        }

        $row = $this->chargeBase($ctx)->open()
            ->selectRaw('count(*) as count, coalesce(sum(amount), 0) as total')
            ->first();

        $total = (float) ($row->total ?? 0);

        return [
            'label' => 'Valor em aberto',
            'value' => $total,
            'formatted' => Money::brl($total),
            'caption' => ((int) ($row->count ?? 0)).' cobrança(s)',
            'color' => 'amber',
            'icon' => 'Wallet',
        ];
    }
}
