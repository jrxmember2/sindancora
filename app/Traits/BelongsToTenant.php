<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = app()->bound('tenant_id') ? app('tenant_id') : null;
            if ($tenantId) {
                $query->where(static::getTenantColumn(), $tenantId);
            }
        });

        static::creating(function (Model $model) {
            if (! $model->{static::getTenantColumn()}) {
                $tenantId = app()->bound('tenant_id') ? app('tenant_id') : null;
                if ($tenantId) {
                    $model->{static::getTenantColumn()} = $tenantId;
                }
            }
        });
    }

    protected static function getTenantColumn(): string
    {
        return 'tenant_id';
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')->where(static::getTenantColumn(), $tenantId);
    }
}
