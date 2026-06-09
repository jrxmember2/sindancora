<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Condominium;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Escopo de condomínios por papel: usuários tenant-wide (papel sem condominium_id) e super admin
 * veem todos os condomínios ativos; usuários escopados por `user_roles.condominium_id` veem
 * apenas o seu escopo. Mesmo critério usado em Funcionários, Cronograma e Relatórios.
 */
trait ScopesCondominiumsByRole
{
    protected function accessibleCondominiums(string $tenantId, ?User $user): Collection
    {
        $query = Condominium::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name');

        if (! $user?->isSuperAdmin() && ! $this->hasTenantWideCondominiumAccess($user)) {
            $ids = $user?->userRoles()
                ->whereNotNull('condominium_id')
                ->pluck('condominium_id')
                ->unique()
                ->values()
                ->all() ?? [];

            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'name']);
    }

    protected function hasTenantWideCondominiumAccess(?User $user): bool
    {
        return (bool) ($user?->isSuperAdmin() || $user?->userRoles()->whereNull('condominium_id')->exists());
    }
}
