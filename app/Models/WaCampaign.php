<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campanha de disparo em massa por WhatsApp. Alvo: moradores (Person com telefone) de um condomínio,
 * opcionalmente segmentado por blocos ou unidades. Os destinatários são "congelados" em
 * wa_campaign_recipients no momento da montagem. Envio com throttle (anti-ban).
 */
class WaCampaign extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey;

    protected $table = 'wa_campaigns';

    protected $fillable = [
        'tenant_id', 'connection_id', 'condominium_id', 'name', 'body', 'media_storage_object_id',
        'target_type', 'block_ids', 'unit_ids', 'throttle_seconds', 'status', 'scheduled_at',
        'total_recipients', 'sent_count', 'failed_count', 'skipped_count',
        'started_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'block_ids' => 'array',
            'unit_ids' => 'array',
            'throttle_seconds' => 'integer',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'draft' => 'Rascunho',
        'scheduled' => 'Agendada',
        'sending' => 'Enviando',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(StorageObject::class, 'media_storage_object_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WaCampaignRecipient::class, 'campaign_id');
    }

    /** Pode ser editada/montada/iniciada? (apenas rascunho ou agendada). */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }
}
