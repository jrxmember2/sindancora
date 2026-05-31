<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\PlanLimit;
use App\Models\Tenant;
use App\Models\TenantLimit;
use App\Models\TenantUsageCounter;
use Illuminate\Support\Facades\DB;

class PlanLimitService
{
    public function check(Tenant $tenant, string $resource, int $increment = 1): void
    {
        $limit = $this->getLimit($tenant, $resource);

        if ($limit === -1) {
            return; // ilimitado
        }

        $current = $this->getCurrent($tenant, $resource);

        if (($current + $increment) > $limit) {
            $plan = $tenant->activePlan();
            throw new PlanLimitException(
                resource: $resource,
                current: $current,
                limit: $limit,
                planName: $plan?->display_name ?? 'desconhecido',
            );
        }
    }

    public function increment(Tenant $tenant, string $resource, int $by = 1): void
    {
        TenantUsageCounter::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->increment('current_value', $by);
    }

    public function decrement(Tenant $tenant, string $resource, int $by = 1): void
    {
        TenantUsageCounter::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->decrement('current_value', max(0, $by));
    }

    public function getLimit(Tenant $tenant, string $resource): int
    {
        // 1. Override específico do tenant tem prioridade
        $override = TenantLimit::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->first();

        if ($override) {
            return $override->limit_value;
        }

        // 2. Busca no plano ativo
        $plan = $tenant->activePlan();
        if (! $plan) {
            return 0;
        }

        return $plan->getLimit($resource);
    }

    public function getCurrent(Tenant $tenant, string $resource): int
    {
        return TenantUsageCounter::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->value('current_value') ?? 0;
    }

    public function syncCounter(Tenant $tenant, string $resource, int $value): void
    {
        TenantUsageCounter::updateOrCreate(
            ['tenant_id' => $tenant->id, 'resource' => $resource],
            ['current_value' => $value],
        );
    }

    public function getUsageSummary(Tenant $tenant): array
    {
        $resources = ['condominiums', 'units', 'users', 'residents', 'storage_mb'];
        $summary = [];

        foreach ($resources as $resource) {
            $current = $this->getCurrent($tenant, $resource);
            $limit = $this->getLimit($tenant, $resource);

            $summary[$resource] = [
                'current' => $current,
                'limit' => $limit,
                'unlimited' => $limit === -1,
                'percentage' => ($limit > 0) ? round(($current / $limit) * 100, 1) : 0,
                'near_limit' => ($limit > 0) && ($current / $limit) > 0.85,
            ];
        }

        return $summary;
    }
}
