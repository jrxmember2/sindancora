<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationProposal extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'quotation_id', 'supplier_id', 'created_by', 'supplier_name',
        'amount', 'execution_days', 'valid_until', 'status', 'submitted_at', 'notes',
    ];

    protected $appends = ['status_label'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'execution_days' => 'integer',
            'valid_until' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'received' => 'Recebida',
        'approved' => 'Aprovada',
        'rejected' => 'Rejeitada',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StorageObject::class, 'entity_id')
            ->where('entity_type', 'quotation_proposal')
            ->whereNull('deleted_at')
            ->orderBy('created_at');
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class, 'quotation_proposal_id');
    }

    public function maintenancePlan(): HasOne
    {
        return $this->hasOne(MaintenancePlan::class, 'quotation_proposal_id');
    }
}
