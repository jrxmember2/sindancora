<?php

namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Support\Money;

/**
 * Contas a pagar em aberto vencendo nos próximos 7 dias (e já vencidas). Alerta.
 */
class PayablesDueResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['level' => 'info', 'count' => 0, 'items' => []]);
        }

        $names = $this->condoNames($ctx);
        $limit = today()->addDays(7)->toDateString();

        $expenses = $this->expenseBase($ctx)->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $limit)
            ->orderBy('due_date')
            ->limit(6)
            ->get(['id', 'condominium_id', 'description', 'amount', 'due_date']);

        $overdueCount = (clone $this->expenseBase($ctx))->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today()->toDateString())
            ->count();

        $items = $expenses->map(fn ($e) => [
            'title' => $e->description ?: 'Conta a pagar',
            'subtitle' => ($names[$e->condominium_id] ?? '').' · vence '.$e->due_date->format('d/m'),
            'value' => Money::brl((float) $e->amount),
            'href' => '/despesas/'.$e->id.'/editar',
            'overdue' => $e->due_date->isPast(),
        ])->all();

        return [
            'level' => $overdueCount > 0 ? 'critical' : ($items === [] ? 'info' : 'warning'),
            'count' => count($items),
            'overdue_count' => $overdueCount,
            'items' => $items,
            'emptyText' => 'Nenhuma conta a vencer nos próximos 7 dias.',
            'href' => '/despesas',
            'icon' => 'Receipt',
        ];
    }
}
