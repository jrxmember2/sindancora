<?php

namespace App\Dashboard\Resolvers\Occurrences;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

/**
 * Ocorrências abertas com SLA vencido ou vencendo nas próximas 24h. Alerta.
 */
class SlaAtRiskResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['level' => 'info', 'count' => 0, 'items' => []]);
        }

        $names = $this->condoNames($ctx);
        $deadline = now()->addDay();

        $items = $this->occurrenceBase($ctx)
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $deadline)
            ->orderBy('due_at')
            ->limit(6)
            ->get(['id', 'condominium_id', 'title', 'due_at']);

        $overdue = (clone $this->occurrenceBase($ctx))
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $list = $items->map(fn ($o) => [
            'title' => $o->title,
            'subtitle' => ($names[$o->condominium_id] ?? '').' · prazo '.$o->due_at->format('d/m H:i'),
            'href' => '/ocorrencias/'.$o->id,
            'overdue' => $o->due_at->isPast(),
        ])->all();

        return [
            'level' => $overdue > 0 ? 'critical' : ($list === [] ? 'info' : 'warning'),
            'count' => count($list),
            'overdue_count' => $overdue,
            'items' => $list,
            'emptyText' => 'Nenhum SLA em risco. 👍',
            'href' => '/ocorrencias',
            'icon' => 'Timer',
        ];
    }
}
