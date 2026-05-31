<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'domain', 'type', 'active', 'verified_at'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
