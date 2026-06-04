<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\Tenant;
use App\Models\WhatsappConnection;

/**
 * Licenciamento das conexões WhatsApp: limite efetivo = limite do plano (ou override) + add-ons
 * ativos. Usado para impedir a criação de conexões acima do contratado (→ 402).
 */
class WhatsappConnectionService
{
    public function __construct(private readonly PlanLimitService $limits) {}

    /** Limite efetivo de conexões do tenant (-1 = ilimitado). Plano/override + add-ons ativos. */
    public function limit(Tenant $tenant): int
    {
        $base = $this->limits->getLimit($tenant, 'whatsapp_connections');

        if ($base === -1) {
            return -1;
        }

        $addons = (int) $tenant->whatsappAddons()
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->sum('quantity');

        return $base + $addons;
    }

    /** Conexões já criadas (contagem viva, ignora soft-deletadas). */
    public function used(Tenant $tenant): int
    {
        return WhatsappConnection::where('tenant_id', $tenant->id)->count();
    }

    public function remaining(Tenant $tenant): ?int
    {
        $limit = $this->limit($tenant);

        return $limit === -1 ? null : max(0, $limit - $this->used($tenant));
    }

    /** Garante que ainda há licença para criar mais uma conexão. */
    public function assertCanCreate(Tenant $tenant): void
    {
        $limit = $this->limit($tenant);

        if ($limit === -1) {
            return;
        }

        if (($this->used($tenant) + 1) > $limit) {
            throw new PlanLimitException(
                resource: 'whatsapp_connections',
                current: $this->used($tenant),
                limit: $limit,
                planName: $tenant->activePlan()?->display_name ?? 'desconhecido',
            );
        }
    }
}
