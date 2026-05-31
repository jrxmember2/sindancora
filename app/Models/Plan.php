<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'name', 'display_name', 'description',
        'price_monthly', 'price_yearly',
        'is_active', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
        ];
    }

    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(PlanModule::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantPlanSubscription::class);
    }

    public function getLimit(string $resource): int
    {
        return $this->limits()->where('resource', $resource)->value('limit_value') ?? -1;
    }

    public function hasModule(string $module): bool
    {
        return $this->modules()->where('module', $module)->where('enabled', true)->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
