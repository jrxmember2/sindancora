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

    public function driveSetting(): HasOne
    {
        return $this->hasOne(TenantDriveSetting::class);
    }

    /** Tem Google Drive conectado e ativo para descarregar mídia de WhatsApp? */
    public function hasActiveDrive(): bool
    {
        return (bool) $this->driveSetting?->isActive();
    }

    /**
     * Política de limpeza automática de mídia de WhatsApp do tenant (em tenants.settings).
     * mode: off (só avisa aos 85%) | date (apaga mídia mais antiga que N dias) | quota (apaga ao 85%).
     *
     * @return array{mode: string, retention_days: int|null}
     */
    public function whatsappCleanupPolicy(): array
    {
        $policy = $this->getSettings('whatsapp_media_cleanup', []);
        $mode = in_array($policy['mode'] ?? null, ['date', 'quota'], true) ? $policy['mode'] : 'off';
        $days = (int) ($policy['retention_days'] ?? 0);

        return [
            'mode' => $mode,
            'retention_days' => $days > 0 ? $days : null,
        ];
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

    /**
     * Logo embutida como data URI base64 — para relatórios em PDF (dompdf), onde URLs assinadas
     * são frágeis. Retorna null se não houver logo ou o arquivo não puder ser lido.
     */
    public function getLogoDataUri(): ?string
    {
        $object = $this->logoObject();
        if (! $object) {
            return null;
        }

        try {
            $contents = \Illuminate\Support\Facades\Storage::disk($object->storage_provider)->get($object->storage_path);
        } catch (\Throwable) {
            return null;
        }

        if ($contents === null) {
            return null;
        }

        $mime = $object->mime_type ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
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
        // Logos ativas (não removidas) do tenant. Não filtra por entity_id: a query já é
        // escopada por tenant + tipo, e o entity_id é redundante (e fonte de bug quando difere).
        $query = StorageObject::where('tenant_id', $this->id)
            ->where('entity_type', self::LOGO_ENTITY)
            ->whereNull('deleted_at');

        // Preferência: o objeto referenciado em settings.brand.logo_storage_object_id.
        $objectId = $this->getLogoStorageObjectId();
        if ($objectId) {
            $object = (clone $query)->find($objectId);
            if ($object) {
                return $object;
            }
        }

        // Fallback: id ausente/defasado em settings → usa a logo ativa mais recente do tenant.
        // Isso evita que o logo "suma" do painel/PDF quando a referência em settings se perde.
        return $query->latest('created_at')->first();
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
