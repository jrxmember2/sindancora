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

class Work extends Model
{
    use BelongsToTenant, HasAttachments, HasAuditLog, HasUuidKey, SoftDeletes;

    public const ATTACHMENT_ENTITY = 'work';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'supplier_id', 'quotation_id', 'quotation_proposal_id',
        'created_by', 'title', 'type', 'status', 'priority', 'description', 'start_date',
        'expected_end_date', 'completed_at', 'budget_amount', 'final_amount',
        'progress_percent', 'responsible_name', 'notes',
    ];

    protected $appends = [
        'status_label',
        'type_label',
        'priority_label',
        'budget_variance',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'completed_at' => 'datetime',
            'budget_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'progress_percent' => 'integer',
        ];
    }

    public const TYPES = [
        'renovation' => 'Reforma',
        'repair' => 'Reparo',
        'construction' => 'Obra',
        'improvement' => 'Melhoria',
        'painting' => 'Pintura',
        'inspection' => 'Vistoria',
        'other' => 'Outro',
    ];

    public const STATUSES = [
        'planned' => 'Planejada',
        'budgeting' => 'Em cotação',
        'approved' => 'Aprovada',
        'in_progress' => 'Em execução',
        'paused' => 'Pausada',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
    ];

    public const PRIORITIES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function quotationProposal(): BelongsTo
    {
        return $this->belongsTo(QuotationProposal::class, 'quotation_proposal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WorkUpdate::class)->latest('occurred_at')->latest();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function getBudgetVarianceAttribute(): ?float
    {
        if ($this->budget_amount === null || $this->final_amount === null) {
            return null;
        }

        return round((float) $this->final_amount - (float) $this->budget_amount, 2);
    }
}
