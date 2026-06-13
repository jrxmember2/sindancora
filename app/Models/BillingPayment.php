<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espelho local de um payment do Asaas (billing SaaS). O Asaas é a fonte da verdade;
 * este registro é sincronizado via webhook + reconciliação diária.
 */
class BillingPayment extends Model
{
    use HasUuidKey;

    /** Status do Asaas que significam pagamento compensado. */
    public const PAID_STATUSES = ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'];

    protected $fillable = [
        'tenant_id', 'billing_subscription_id',
        'asaas_payment_id', 'asaas_subscription_id', 'asaas_customer_id',
        'status', 'billing_type', 'value', 'net_value', 'due_date', 'payment_date',
        'invoice_url', 'bank_slip_url', 'is_first_payment',
        'invoice_id', 'nfse_status', 'nfse_pdf_url', 'nfse_xml_url', 'nfse_error',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'net_value' => 'decimal:2',
            'due_date' => 'date',
            'payment_date' => 'date',
            'is_first_payment' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, self::PAID_STATUSES, true);
    }
}
