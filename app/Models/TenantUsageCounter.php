<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageCounter extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'resource', 'current_value', 'reset_at'];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'reset_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
