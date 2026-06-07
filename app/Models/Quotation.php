<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'created_by', 'approved_by', 'approved_proposal_id',
        'category', 'title', 'description', 'status', 'response_deadline', 'approved_at', 'notes',
    ];

    protected $appends = ['status_label'];

    protected function casts(): array
    {
        return [
            'response_deadline' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public const CATEGORIES = [
        'maintenance' => 'Manutenção',
        'repair' => 'Reparo pontual',
        'works' => 'Obra',
        'cleaning' => 'Limpeza',
        'security' => 'Segurança',
        'purchase' => 'Compra',
        'contract' => 'Contrato',
        'other' => 'Outro',
    ];

    public const STATUSES = [
        'draft' => 'Rascunho',
        'collecting' => 'Em cotação',
        'approved' => 'Aprovado',
        'rejected' => 'Reprovado',
        'cancelled' => 'Cancelado',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(QuotationProposal::class)->orderBy('amount');
    }

    public function approvedProposal(): BelongsTo
    {
        return $this->belongsTo(QuotationProposal::class, 'approved_proposal_id');
    }
}
