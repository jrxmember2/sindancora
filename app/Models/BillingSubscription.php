<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Assinatura SaaS do tenant: espelho da subscription do Asaas + máquina de estados da cobrança
 * da plataforma (active, overdue, suspended, canceled, grace_manual, grace_trust).
 */
class BillingSubscription extends Model
{
    use HasUuidKey;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_GRACE_MANUAL = 'grace_manual';

    public const STATUS_GRACE_TRUST = 'grace_trust';

    protected $fillable = [
        'tenant_id', 'plan_id', 'asaas_customer_id', 'asaas_subscription_id',
        'billing_cycle', 'billing_type', 'value', 'status', 'next_due_date',
        'started_at', 'canceled_at',
        'grace_until', 'grace_reason', 'grace_granted_by', 'grace_granted_at',
        'trust_grace_count', 'last_trust_grace_at', 'dunning_state',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'next_due_date' => 'date',
            'started_at' => 'datetime',
            'canceled_at' => 'datetime',
            'grace_until' => 'date',
            'grace_granted_at' => 'datetime',
            'last_trust_grace_at' => 'datetime',
            'dunning_state' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }

    /** Em alguma forma de carência (acesso liberado, mas sinalizado). */
    public function inGrace(): bool
    {
        return in_array($this->status, [self::STATUS_GRACE_MANUAL, self::STATUS_GRACE_TRUST], true);
    }

    /** Estados que mantêm o acesso do tenant liberado. */
    public function grantsAccess(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE, self::STATUS_OVERDUE,
            self::STATUS_GRACE_MANUAL, self::STATUS_GRACE_TRUST,
        ], true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
