<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAttachments;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryRecord extends Model
{
    use BelongsToTenant, HasAttachments, HasUuidKey;

    public const ATTACHMENT_ENTITY = 'disciplinary_record';

    public const TYPES = [
        'warning' => 'Advertencia',
        'fine' => 'Multa',
    ];

    public const STATUSES = [
        'issued' => 'Emitida',
        'acknowledged' => 'Ciente',
        'cancelled' => 'Cancelada',
    ];

    protected $fillable = [
        'tenant_id',
        'condominium_id',
        'unit_id',
        'person_id',
        'charge_id',
        'type',
        'status',
        'title',
        'rule_reference',
        'description',
        'occurred_on',
        'amount',
        'due_date',
        'issued_at',
        'acknowledged_at',
        'acknowledged_by',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'amount' => 'decimal:2',
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

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleToResident(Builder $query, array $unitIds): Builder
    {
        return $query->whereIn('unit_id', $unitIds)->where('status', '!=', 'cancelled');
    }
}
