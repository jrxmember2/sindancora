<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuidKey, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'document', 'email', 'phone', 'status', 'plan_id', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function activeSubscription(): HasOne
    {
        // Não usar latestOfMany(): em Postgres com PK UUID ele gera MAX(uuid) como
        // critério de desempate, e não existe a função max(uuid). Ordenar por starts_at resolve.
        return $this->hasOne(TenantPlanSubscription::class)
            ->where('status', 'active')
            ->latest('starts_at');
    }

    public function activePlan(): ?Plan
    {
        return $this->activeSubscription?->plan ?? $this->plan;
    }

    public function limits(): HasMany
    {
        return $this->hasMany(TenantLimit::class);
    }

    public function usageCounters(): HasMany
    {
        return $this->hasMany(TenantUsageCounter::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function storageAddons(): HasMany
    {
        return $this->hasMany(TenantStorageAddon::class)->where('active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function getSettings(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getBrandName(): string
    {
        return $this->getSettings('brand.name', $this->name);
    }

    public function getLogoUrl(): ?string
    {
        return $this->getSettings('brand.logo_url');
    }

    public function getPrimaryColor(): string
    {
        return $this->getSettings('brand.primary_color', '#1e40af');
    }
}
