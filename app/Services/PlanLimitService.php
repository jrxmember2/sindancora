<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\Condominium;
use App\Models\StorageObject;
use App\Models\Tenant;
use App\Models\TenantLimit;
use App\Models\TenantUsageCounter;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanLimitService
{
    private const LIVE_RESOURCES = ['condominiums', 'units', 'users', 'residents', 'storage_mb'];
    private const MONTHLY_RESOURCES = ['announcements_monthly', 'emails_monthly', 'api_calls_monthly', 'ai_interactions_monthly'];

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
        if ($this->usesLiveSource($resource)) {
            $this->syncCounter($tenant, $resource, $this->currentFromLiveSource($tenant, $resource));

            return;
        }

        $counter = $this->counter($tenant, $resource);
        if ($this->usesMonthlyReset($resource)) {
            $counter = $this->refreshMonthlyCounter($tenant, $resource, $counter);
        }

        $counter->increment('current_value', $by);
    }

    public function incrementBy(Tenant $tenant, string $resource, int $by): void
    {
        $this->increment($tenant, $resource, $by);
    }

    public function decrement(Tenant $tenant, string $resource, int $by = 1): void
    {
        if ($this->usesLiveSource($resource)) {
            $this->syncCounter($tenant, $resource, $this->currentFromLiveSource($tenant, $resource));

            return;
        }

        $counter = $this->counter($tenant, $resource);
        if ($this->usesMonthlyReset($resource)) {
            $counter = $this->refreshMonthlyCounter($tenant, $resource, $counter);
        }

        $counter->decrement('current_value', max(0, $by));
    }

    private function counter(Tenant $tenant, string $resource): TenantUsageCounter
    {
        return TenantUsageCounter::firstOrCreate(
            ['tenant_id' => $tenant->id, 'resource' => $resource],
            ['current_value' => 0],
        );
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
        if ($this->usesLiveSource($resource)) {
            $current = $this->currentFromLiveSource($tenant, $resource);
            $this->syncCounter($tenant, $resource, $current);

            return $current;
        }

        if ($this->usesMonthlyReset($resource)) {
            return $this->refreshMonthlyCounter($tenant, $resource)->current_value;
        }

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
        $resources = ['condominiums', 'units', 'users', 'residents', 'storage_mb', 'ai_interactions_monthly'];
        $summary = [];

        foreach ($resources as $resource) {
            $summary[$resource] = $this->getResourceUsage($tenant, $resource);
        }

        return $summary;
    }

    public function getResourceUsage(Tenant $tenant, string $resource): array
    {
        $current = $this->getCurrent($tenant, $resource);
        $limit = $this->getLimit($tenant, $resource);
        $override = TenantLimit::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->first();
        $counter = TenantUsageCounter::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->first();

        return [
            'resource' => $resource,
            'current' => $current,
            'limit' => $limit,
            'unlimited' => $limit === -1,
            'remaining' => $limit === -1 ? null : max(0, $limit - $current),
            'percentage' => ($limit > 0) ? min(100, round(($current / $limit) * 100, 1)) : 0,
            'near_limit' => ($limit > 0) && ($current / $limit) > 0.85,
            'exhausted' => $limit !== -1 && $current >= $limit,
            'reset_at' => $counter?->reset_at?->toIso8601String(),
            'has_override' => (bool) $override,
            'override_limit' => $override?->limit_value,
        ];
    }

    public function syncPermanentCounters(Tenant $tenant): void
    {
        foreach (self::LIVE_RESOURCES as $resource) {
            $this->syncCounter($tenant, $resource, $this->currentFromLiveSource($tenant, $resource));
        }
    }

    private function usesLiveSource(string $resource): bool
    {
        return in_array($resource, self::LIVE_RESOURCES, true);
    }

    private function usesMonthlyReset(string $resource): bool
    {
        return in_array($resource, self::MONTHLY_RESOURCES, true);
    }

    private function refreshMonthlyCounter(Tenant $tenant, string $resource, ?TenantUsageCounter $counter = null): TenantUsageCounter
    {
        $counter ??= $this->counter($tenant, $resource);

        if (! $counter->reset_at) {
            $counter->forceFill(['reset_at' => $this->nextMonthlyResetAt($tenant)])->save();

            return $counter->refresh();
        }

        if ($counter->reset_at->isPast()) {
            $counter->forceFill([
                'current_value' => 0,
                'reset_at' => $this->nextMonthlyResetAt($tenant),
            ])->save();

            return $counter->refresh();
        }

        return $counter;
    }

    private function nextMonthlyResetAt(Tenant $tenant): Carbon
    {
        $now = now();
        $anchor = $tenant->activeSubscription()->first()?->starts_at
            ?? $tenant->created_at
            ?? $now;

        $cursor = $now->copy()->startOfMonth();

        do {
            $day = min($anchor->day, $cursor->copy()->endOfMonth()->day);
            $candidate = Carbon::create(
                $cursor->year,
                $cursor->month,
                $day,
                $anchor->hour,
                $anchor->minute,
                $anchor->second,
                $now->timezone,
            );

            if ($candidate->greaterThan($now)) {
                return $candidate;
            }

            $cursor->addMonthNoOverflow();
        } while (true);
    }

    private function currentFromLiveSource(Tenant $tenant, string $resource): int
    {
        return match ($resource) {
            'condominiums' => Condominium::where('tenant_id', $tenant->id)->count(),
            'units' => Unit::where('tenant_id', $tenant->id)->count(),
            'users' => User::where('tenant_id', $tenant->id)->count(),
            'residents' => User::where('tenant_id', $tenant->id)
                ->where(fn ($q) => $q
                    ->whereNotNull('person_id')
                    ->orWhereHas('userRoles.role', fn ($role) => $role->where('name', 'morador')))
                ->count(),
            'storage_mb' => (int) ceil(StorageObject::where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->sum('file_size_bytes') / 1024 / 1024),
            default => 0,
        };
    }
}
