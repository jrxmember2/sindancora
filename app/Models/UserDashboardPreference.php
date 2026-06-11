<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferências do dashboard por usuário (e tenant): widgets ocultos, ordem
 * personalizada e filtros salvos. Uma linha por (tenant_id, user_id).
 */
class UserDashboardPreference extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'hidden_widgets',
        'widget_order',
        'filters',
    ];

    protected function casts(): array
    {
        return [
            'hidden_widgets' => 'array',
            'widget_order' => 'array',
            'filters' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
