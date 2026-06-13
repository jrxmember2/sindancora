<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dispositivo móvel registrado para push (FCM). Um registro por token vigente;
 * o app re-registra no login e na rotação do token FCM.
 */
class UserDevice extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'fcm_token',
        'platform',
        'app_version',
        'device_name',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
