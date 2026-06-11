<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Models\Charge;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Unidades inadimplentes (com ao menos uma cobrança vencida) e o total devido,
 * calculado pelo currentAmount() de cada cobrança (principal + multa/juros).
 */
class DelinquentUnitsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Inadimplência']);
        }

        /** @var Collection<int, Charge> $overdue */
        $overdue = $this->chargeBase($ctx)->overdue()
            ->get(['id', 'unit_id', 'amount', 'fine_rate', 'interest_rate', 'due_date', 'status']);

        $units = $overdue->pluck('unit_id')->filter()->unique()->count();
        $total = round($overdue->sum(fn (Charge $c) => $c->currentAmount()), 2);

        return [
            'label' => 'Unidades inadimplentes',
            'value' => $units,
            'formatted' => number_format($units, 0, ',', '.'),
            'caption' => Money::brl($total).' em atraso',
            'color' => $units > 0 ? 'red' : 'emerald',
            'icon' => 'AlertTriangle',
        ];
    }
}
