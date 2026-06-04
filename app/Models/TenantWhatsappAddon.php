<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantWhatsappAddon extends Model
{
    use HasUuidKey;

    protected $table = 'tenant_whatsapp_addons';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'quantity', 'price_paid', 'active', 'starts_at', 'ends_at', 'added_by', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
