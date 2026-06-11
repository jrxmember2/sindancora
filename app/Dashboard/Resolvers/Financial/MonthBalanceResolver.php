<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Support\Money;

/**
 * Saldo do mês: recebido (cobranças pagas) menos despesas pagas no mês corrente.
 */
class MonthBalanceResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Saldo do mês']);
        }

        $start = now()->startOfMonth();

        $received = (float) $this->chargeBase($ctx)->where('status', 'paid')
            ->where('paid_at', '>=', $start)->sum('paid_amount');

        $spent = (float) $this->expenseBase($ctx)->where('status', 'paid')
            ->where('paid_at', '>=', $start)->sum('paid_amount');

        $balance = round($received - $spent, 2);

        return [
            'label' => 'Saldo do mês',
            'value' => $balance,
            'formatted' => Money::brl($balance),
            'caption' => 'Recebido '.Money::compactBrl($received).' · Pago '.Money::compactBrl($spent),
            'color' => $balance >= 0 ? 'emerald' : 'red',
            'icon' => 'Scale',
        ];
    }
}
