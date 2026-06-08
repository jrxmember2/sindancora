<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'storage_object_id', 'uploaded_by',
        'title', 'description', 'category', 'visibility',
        'valid_from', 'valid_until', 'renewal_alert_days', 'is_current',
        'is_ai_searchable', 'expiry_notified_at',
    ];

    protected $appends = ['expiry_status', 'days_until_expiry'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_current' => 'boolean',
            'is_ai_searchable' => 'boolean',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public const CATEGORIES = [
        'convention' => 'Convenção',
        'regulation' => 'Regimento interno',
        'minutes' => 'Ata',
        'contract' => 'Contrato',
        'circular' => 'Circular',
        'receipt' => 'Comprovante',
        'other' => 'Outro',
    ];

    public const VISIBILITIES = [
        'residents' => 'Moradores',
        'restricted' => 'Restrito (administração)',
    ];

    /** Mapeia a visibilidade do documento para a visibilidade do StorageObject. */
    public const STORAGE_VISIBILITY = [
        'residents' => 'public_to_residents',
        'restricted' => 'tenant',
    ];

    /** Dias até o vencimento (negativo = vencido); null se não tem validade. */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->valid_until) {
            return null;
        }

        return (int) round(now()->startOfDay()->diffInDays($this->valid_until->startOfDay(), false));
    }

    /** Situação da vigência: valid | expiring | expired | null (sem validade). */
    public function getExpiryStatusAttribute(): ?string
    {
        $days = $this->days_until_expiry;
        if ($days === null) {
            return null;
        }
        if ($days < 0) {
            return 'expired';
        }

        return $days <= ($this->renewal_alert_days ?? 30) ? 'expiring' : 'valid';
    }

    /** Documentos vencendo dentro da janela de alerta e ainda não notificados. */
    public function scopeDueForExpiryAlert($query)
    {
        return $query->whereNotNull('valid_until')
            ->whereNull('expiry_notified_at')
            ->whereRaw('valid_until <= (CURRENT_DATE + COALESCE(renewal_alert_days, 30) * INTERVAL \'1 day\')');
    }

    /** Documentos que podem ser usados como fonte pelo assistente de IA. */
    public function scopeSearchableByAi($query)
    {
        return $query->where('is_current', true)
            ->where('is_ai_searchable', true);
    }

    public function isSearchableByAi(): bool
    {
        return (bool) $this->is_current && (bool) $this->is_ai_searchable;
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function storageObject(): BelongsTo
    {
        return $this->belongsTo(StorageObject::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
