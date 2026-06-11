<?php

namespace App\Dashboard\Resolvers\Operations;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

/**
 * Próximas manutenções preventivas (planos ativos com vencimento mais próximo).
 * Tabela resumida.
 */
class UpcomingMaintenanceResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['rows' => []]);
        }

        $plans = $this->maintenanceBase($ctx)
            ->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->with('condominium:id,name')
            ->orderBy('next_due_date')
            ->limit(6)
            ->get(['id', 'condominium_id', 'name', 'next_due_date', 'alert_days']);

        $rows = $plans->map(fn ($p) => [
            'name' => $p->name,
            'condominium' => $p->condominium?->name ?? '—',
            'due' => $p->next_due_date?->format('d/m/Y'),
            'status' => $p->status,
            'status_label' => match ($p->status) {
                'overdue' => 'Atrasada',
                'due_soon' => 'Em breve',
                default => 'No prazo',
            },
            'status_color' => match ($p->status) {
                'overdue' => 'red',
                'due_soon' => 'amber',
                default => 'emerald',
            },
        ])->all();

        if ($rows === []) {
            return $this->empty(['rows' => [], 'emptyText' => 'Nenhuma manutenção programada.']);
        }

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Plano'],
                ['key' => 'condominium', 'label' => 'Condomínio'],
                ['key' => 'due', 'label' => 'Vencimento'],
                ['key' => 'status_label', 'label' => 'Situação', 'badge' => true],
            ],
            'rows' => $rows,
            'href' => '/manutencoes',
        ];
    }
}
