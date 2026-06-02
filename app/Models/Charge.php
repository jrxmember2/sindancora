<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Charge extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'unit_id', 'person_id', 'batch_id',
        'type', 'description', 'reference_month', 'amount', 'due_date',
        'fine_rate', 'interest_rate', 'status', 'paid_at', 'paid_amount',
        'payment_method', 'receipt_storage_object_id', 'notes', 'created_by',
        'gateway', 'gateway_payment_id', 'gateway_status', 'invoice_url',
        'bank_slip_url', 'bank_slip_line', 'pix_payload', 'pix_qrcode', 'gateway_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'gateway_synced_at' => 'datetime',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'fine_rate' => 'decimal:2',
            'interest_rate' => 'decimal:2',
        ];
    }

    public const TYPES = [
        'condo_fee' => 'Taxa condominial',
        'extra' => 'Taxa extra',
        'fine' => 'Multa',
        'other' => 'Outro',
    ];

    public const STATUSES = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'overdue' => 'Vencido',
        'cancelled' => 'Cancelado',
    ];

    protected static function booted(): void
    {
        static::created(function (Charge $charge) {
            app(\App\Services\WebhookService::class)->dispatch($charge->tenant_id, 'charge.created', $charge->toWebhookArray());
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Payload compacto para webhooks de saída. */
    public function toWebhookArray(): array
    {
        return [
            'id' => $this->id,
            'condominium_id' => $this->condominium_id,
            'unit_id' => $this->unit_id,
            'person_id' => $this->person_id,
            'type' => $this->type,
            'description' => $this->description,
            'reference_month' => $this->reference_month,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date?->toDateString(),
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StorageObject::class, 'receipt_storage_object_id');
    }

    /** Cobranças em aberto e já vencidas (por data ou status). */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('due_date', '<', now()->toDateString());
    }

    /** Cobranças em aberto (não pagas nem canceladas). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'overdue']);
    }

    /** True quando a cobrança já tem boleto/PIX emitido no gateway. */
    public function hasGatewayCharge(): bool
    {
        return $this->gateway_payment_id !== null;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['pending', 'overdue'], true)
            && $this->due_date !== null
            && $this->due_date->lt(Carbon::today());
    }

    /**
     * Valor atual devido: principal + multa (%) + juros (% ao mês, pró-rata por dia) se vencida.
     * Cálculo derivado — não é persistido.
     */
    public function currentAmount(): float
    {
        $amount = (float) $this->amount;

        if (! $this->isOverdue()) {
            return round($amount, 2);
        }

        $days = Carbon::today()->diffInDays($this->due_date);
        $fine = $amount * ((float) $this->fine_rate / 100);
        $interest = $amount * ((float) $this->interest_rate / 100) * ($days / 30);

        return round($amount + $fine + $interest, 2);
    }
}
