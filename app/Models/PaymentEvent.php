<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditoria de eventos do webhook do Asaas (billing SaaS). A unicidade de asaas_event_id
 * garante a idempotência: o mesmo evento reprocessado é ignorado.
 */
class PaymentEvent extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'asaas_event_id', 'event', 'asaas_payment_id', 'payload',
        'processed', 'processed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
