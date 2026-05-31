<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStorageAddon extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'package_id', 'size_gb', 'price_paid',
        'active', 'starts_at', 'ends_at', 'added_by', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StorageQuotaPackage::class);
    }
}
