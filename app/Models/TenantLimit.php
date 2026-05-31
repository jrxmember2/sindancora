<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantLimit extends Model
{
    use HasUuidKey;

    protected $fillable = ['tenant_id', 'resource', 'limit_value', 'reason', 'set_by'];

    protected function casts(): array
    {
        return ['limit_value' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
