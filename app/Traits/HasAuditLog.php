<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait HasAuditLog
{
    protected static function bootHasAuditLog(): void
    {
        static::created(fn (Model $model) => static::writeAuditLog('created', $model, [], $model->toArray()));
        static::updated(fn (Model $model) => static::writeAuditLog('updated', $model, $model->getOriginal(), $model->getDirty()));
        static::deleted(fn (Model $model) => static::writeAuditLog('deleted', $model, $model->toArray(), []));
    }

    protected static function writeAuditLog(string $action, Model $model, array $oldValues, array $newValues): void
    {
        try {
            $hidden = $model->getHidden();
            $oldValues = array_diff_key($oldValues, array_flip($hidden));
            $newValues = array_diff_key($newValues, array_flip($hidden));

            AuditLog::create([
                'tenant_id'  => $model->{static::getAuditTenantId($model)},
                'user_id'    => Auth::id(),
                'action'     => $action,
                'entity'     => class_basename($model),
                'entity_id'  => $model->getKey(),
                'old_values' => empty($oldValues) ? null : $oldValues,
                'new_values' => empty($newValues) ? null : $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'url'        => Request::fullUrl(),
            ]);
        } catch (\Exception) {
            // Nunca deixar auditoria quebrar a operação principal
        }
    }

    private static function getAuditTenantId(Model $model): ?string
    {
        if (isset($model->tenant_id)) {
            return $model->tenant_id;
        }
        return app()->bound('tenant_id') ? app('tenant_id') : null;
    }
}
