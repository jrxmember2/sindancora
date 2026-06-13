<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Condominium;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

/**
 * Base dos endpoints consumidos pelo app móvel do tenant (síndico).
 *
 * Convenções: auth `auth:sanctum` (token de usuário), gating pelas MESMAS
 * permissões/módulos do painel (middleware `permission:`), tenant resolvido
 * pelo host (ResolveTenant) e regra de negócio reusada dos services existentes.
 */
#[OA\Tag(name: 'App', description: 'Endpoints do aplicativo móvel do tenant')]
abstract class AppController extends ApiController
{
    protected function tenant(): Tenant
    {
        return app('tenant');
    }

    /** Garante que o registro pertence ao tenant da requisição (rota por host). */
    protected function authorizeTenant(Model $model): Model
    {
        abort_unless($model->getAttribute('tenant_id') === $this->tenant()->id, 403);

        return $model;
    }

    /** Opções id/name de condomínios do tenant (para selects do app). */
    protected function condominiumOptions(): Collection
    {
        return Condominium::where('tenant_id', $this->tenant()->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
