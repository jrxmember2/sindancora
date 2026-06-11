<?php

namespace App\Dashboard\Concerns;

use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Camada fina sobre ScopesCondominiumsByRole para os resolvers do dashboard.
 * Reaproveita exatamente o mesmo critério de escopo por papel usado em
 * Funcionários, Cronograma e Relatórios.
 */
trait ScopesDashboard
{
    use ScopesCondominiumsByRole;

    /**
     * Condomínios acessíveis ao usuário no tenant (id => name).
     */
    public function accessibleCondominiumsFor(string $tenantId, ?User $user): Collection
    {
        return $this->accessibleCondominiums($tenantId, $user);
    }
}
