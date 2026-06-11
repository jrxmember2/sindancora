<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAttachments;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encomenda/correspondência recebida na portaria, vinculada a uma unidade. O porteiro registra a
 * chegada (notifica o morador) e dá baixa na retirada. Foto opcional via HasAttachments.
 */
class Parcel extends Model
{
    use BelongsToTenant, HasAttachments, HasUuidKey;

    public const ATTACHMENT_ENTITY = 'parcel';

    public const STATUSES = [
        'awaiting' => 'Aguardando retirada',
        'picked_up' => 'Retirada',
    ];

    protected $fillable = [
        'tenant_id', 'condominium_id', 'unit_id', 'description', 'carrier', 'tracking_code',
        'status', 'received_by', 'received_at', 'picked_up_by', 'picked_up_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'picked_up_at' => 'datetime',
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

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }

    public function isAwaiting(): bool
    {
        return $this->status === 'awaiting';
    }

    /** Dá baixa na retirada (idempotente). */
    public function markPickedUp(?User $by): void
    {
        if ($this->status === 'picked_up') {
            return;
        }

        $this->forceFill([
            'status' => 'picked_up',
            'picked_up_by' => $by?->id,
            'picked_up_at' => now(),
        ])->save();
    }
}
