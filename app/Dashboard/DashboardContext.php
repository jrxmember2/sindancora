<?php

namespace App\Dashboard;

use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

/**
 * Contexto compartilhado pelos resolvers de widget. Carrega o tenant, o usuário
 * autenticado, o conjunto de condomínios acessíveis (já resolvido pelo escopo de
 * papel) e os filtros globais aplicados (período, condomínio selecionado, status).
 *
 * Imutável: criado pelo DashboardService a cada requisição.
 */
final class DashboardContext
{
    /**
     * @param  array<int, string>  $condominiumIds  IDs de condomínios acessíveis ao usuário (escopo resolvido).
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $user,
        public readonly array $condominiumIds,
        public readonly ?string $selectedCondominiumId,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?string $status = null,
    ) {}

    /**
     * IDs efetivamente usados nas consultas: se um condomínio específico foi
     * selecionado no filtro (e é acessível), restringe a ele; senão usa todos
     * os acessíveis.
     *
     * @return array<int, string>
     */
    public function scopeIds(): array
    {
        if ($this->selectedCondominiumId && in_array($this->selectedCondominiumId, $this->condominiumIds, true)) {
            return [$this->selectedCondominiumId];
        }

        return $this->condominiumIds;
    }

    /** Não há nenhum condomínio acessível — widgets devem cair em empty state. */
    public function hasNoScope(): bool
    {
        return $this->scopeIds() === [];
    }

    /** Chave estável para cache, sensível a tenant + escopo + filtros. */
    public function cacheKey(string $widgetKey): string
    {
        return implode(':', [
            'dashboard',
            $this->tenant->id,
            md5(implode(',', $this->scopeIds())),
            $this->from->toDateString(),
            $this->to->toDateString(),
            $this->status ?? 'all',
            $widgetKey,
        ]);
    }
}
