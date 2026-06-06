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
        'due_at', 'first_response_at', 'sla_notified_at',
    ];

    protected $appends = ['sla_status'];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'sla_notified_at' => 'datetime',
        ];
    }

    /** Prazo padrão de atendimento (em dias) por prioridade — sobreposto pelo OccurrenceSlaSetting do tenant. */
    public const SLA_DEFAULT_DAYS = [
        'low' => 7,
        'normal' => 5,
        'high' => 2,
        'urgent' => 1,
    ];

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

    /** Situação do SLA: null (sem prazo ou encerrada) | overdue | due_soon (≤24h) | on_time. */
    public function getSlaStatusAttribute(): ?string
    {
        if (! $this->due_at || $this->status === 'closed') {
            return null;
        }

        $hours = now()->diffInHours($this->due_at, false);

        if ($hours < 0) {
            return 'overdue';
        }

        return $hours <= 24 ? 'due_soon' : 'on_time';
    }

    /** Ocorrências abertas no prazo de alerta ainda não notificadas neste ciclo. */
    public function scopeDueForSlaAlert($query)
    {
        return $query->where('status', '!=', 'closed')
            ->whereNotNull('due_at')
            ->whereNull('sla_notified_at')
            ->where('due_at', '<=', now()->addDay());
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
