<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Condominium extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    public const LOGO_ENTITY = 'condominium_logo';

    protected $table = 'condominiums';

    protected $fillable = [
        'tenant_id', 'name', 'cnpj', 'email', 'phone',
        'zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
        'settings', 'status',
    ];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function managers(): HasMany
    {
        return $this->hasMany(CondominiumManager::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function activeManagers(): HasMany
    {
        return $this->hasMany(CondominiumManager::class)->whereNull('end_date');
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function botSetting(): HasOne
    {
        return $this->hasOne(WhatsappBotSetting::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->street, $this->number, $this->complement, $this->neighborhood, $this->city, $this->state])
            ->filter()
            ->implode(', ');
    }

    public function getLogoStorageObjectId(): ?string
    {
        return data_get($this->settings, 'brand.logo_storage_object_id')
            ?? data_get($this->settings, 'logo_storage_object_id');
    }

    public function logoObject(): ?StorageObject
    {
        $objectId = $this->getLogoStorageObjectId();

        if (! $objectId) {
            return null;
        }

        return StorageObject::where('tenant_id', $this->tenant_id)
            ->whereNull('deleted_at')
            ->find($objectId);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if ($legacyUrl = data_get($this->settings, 'brand.logo_url')) {
            return $legacyUrl;
        }

        $object = $this->logoObject();

        return $object ? app(StorageService::class)->getSignedUrl($object) : null;
    }
}
