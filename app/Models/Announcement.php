<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAttachments;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use BelongsToTenant, HasAttachments, HasAuditLog, HasUuidKey, SoftDeletes;

    public const ATTACHMENT_ENTITY = 'announcement';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'created_by', 'title', 'body',
        'category', 'urgency', 'status', 'published_at', 'publish_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public const CATEGORIES = [
        'general' => 'Geral',
        'maintenance' => 'Manutenção',
        'financial' => 'Financeiro',
        'assembly' => 'Assembleia',
        'event' => 'Evento',
        'security' => 'Segurança',
    ];

    public const URGENCIES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /** Comunicados já publicados e ainda dentro da validade. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
