<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Models\Charge;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Ranking dos condomínios com maior inadimplência (soma do currentAmount das
 * cobranças vencidas). Barras horizontais. Widget pesado (lazy).
 */
class DelinquencyRankingResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty();
        }

        $names = $this->condoNames($ctx);

        /** @var Collection<int, Charge> $overdue */
        $overdue = $this->chargeBase($ctx)->overdue()
            ->get(['id', 'condominium_id', 'amount', 'fine_rate', 'interest_rate', 'due_date', 'status']);

        $items = $overdue->groupBy('condominium_id')
            ->map(fn (Collection $charges, $condoId) => [
                'label' => $names[$condoId] ?? 'Condomínio',
                'value' => round($charges->sum(fn (Charge $c) => $c->currentAmount()), 2),
            ])
            ->sortByDesc('value')
            ->take(8)
            ->map(fn (array $item) => [
                'label' => $item['label'],
                'value' => $item['value'],
                'formatted' => Money::brl($item['value']),
            ])
            ->values()
            ->all();

        if ($items === []) {
            return $this->empty(['emptyText' => 'Nenhuma inadimplência registrada.']);
        }

        return [
            'items' => $items,
            'color' => 'red',
        ];
    }
}
