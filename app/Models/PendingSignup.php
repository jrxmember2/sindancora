<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pré-cadastro do comprador aguardando a compensação do pagamento. Só vira tenant após o
 * primeiro pagamento confirmado/recebido (provisionamento automático e idempotente).
 */
class PendingSignup extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'plan_id', 'billing_cycle', 'billing_type', 'value',
        'company_name', 'document', 'email', 'phone', 'admin_name',
        'asaas_customer_id', 'asaas_subscription_id', 'first_payment_id',
        'status', 'tenant_id', 'error', 'paid_at', 'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'paid_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isProvisioned(): bool
    {
        return $this->status === 'provisioned';
    }
}
