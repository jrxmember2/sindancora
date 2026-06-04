<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAttachments;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Occurrence extends Model
{
    use BelongsToTenant, HasAttachments, HasAuditLog, HasUuidKey, SoftDeletes;

    public const ATTACHMENT_ENTITY = 'occurrence';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'unit_id', 'created_by', 'assigned_to',
        'title', 'description', 'category', 'priority', 'status', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public const CATEGORIES = [
        'maintenance' => 'Manutenção',
        'cleaning' => 'Limpeza',
        'security' => 'Segurança',
        'noise' => 'Barulho',
        'infraction' => 'Infração',
        'financial' => 'Financeiro',
        'other' => 'Outro',
    ];

    public const PRIORITIES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    public const STATUSES = [
        'open' => 'Aberta',
        'in_progress' => 'Em Andamento',
        'closed' => 'Encerrada',
    ];

    protected static function booted(): void
    {
        static::created(function (Occurrence $occurrence) {
            app(\App\Services\WebhookService::class)->dispatch($occurrence->tenant_id, 'occurrence.created', $occurrence->toWebhookArray());
        });
    }

    /** Payload compacto para webhooks de saída. */
    public function toWebhookArray(): array
    {
        return [
            'id' => $this->id,
            'condominium_id' => $this->condominium_id,
            'unit_id' => $this->unit_id,
            'title' => $this->title,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(OccurrenceComment::class)->orderBy('created_at');
    }
}
