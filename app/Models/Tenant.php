<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuidKey, SoftDeletes;

    public const LOGO_ENTITY = 'tenant_logo';

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

    public function paymentSetting(): HasOne
    {
        return $this->hasOne(TenantPaymentSetting::class)->where('provider', 'asaas');
    }

    public function whatsappSetting(): HasOne
    {
        return $this->hasOne(TenantWhatsappSetting::class);
    }

    public function whatsappConnections(): HasMany
    {
        return $this->hasMany(WhatsappConnection::class);
    }

    public function whatsappAddons(): HasMany
    {
        return $this->hasMany(TenantWhatsappAddon::class)->where('active', true);
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
        if ($object = $this->logoObject()) {
            return app(StorageService::class)->getSignedUrl($object);
        }

        return $this->getSettings('brand.logo_url');
    }

    public function getPrimaryColor(): string
    {
        return $this->getSettings('brand.primary_color', '#1e40af');
    }

    public function getLogoStorageObjectId(): ?string
    {
        return $this->getSettings('brand.logo_storage_object_id');
    }

    public function logoObject(): ?StorageObject
    {
        $objectId = $this->getLogoStorageObjectId();

        if (! $objectId) {
            return null;
        }

        return StorageObject::where('tenant_id', $this->id)
            ->where('entity_type', self::LOGO_ENTITY)
            ->where('entity_id', $this->id)
            ->whereNull('deleted_at')
            ->find($objectId);
    }

    public function getReportProfile(): array
    {
        return [
            'person_type' => $this->getSettings('profile.person_type', 'company'),
            'legal_name' => $this->getSettings('profile.legal_name', $this->name),
            'trade_name' => $this->getSettings('profile.trade_name', $this->getBrandName()),
            'document' => $this->getSettings('profile.document', $this->document),
            'email' => $this->getSettings('profile.email', $this->email),
            'phone' => $this->getSettings('profile.phone', $this->phone),
            'address' => $this->getSettings('profile.address', []),
        ];
    }
}
