<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPlanSubscription extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'tenant_id', 'plan_id', 'status',
        'trial_ends_at', 'starts_at', 'ends_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
