<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha do tempo financeira por tenant: pagamentos, bloqueios, desbloqueios, carências e e-mails.
 */
class BillingTimelineEntry extends Model
{
    use HasUuidKey;

    protected $table = 'billing_timeline';

    protected $fillable = [
        'tenant_id', 'type', 'description', 'meta', 'actor_id', 'actor_name',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
