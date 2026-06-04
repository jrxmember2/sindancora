<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class VisitorAuthorization extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $table = 'visitor_authorizations';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'unit_id', 'created_by',
        'visitor_name', 'visitor_document', 'visitor_phone',
        'type', 'valid_from', 'valid_until', 'token', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public const TYPES = [
        'single' => 'Visita única',
        'recurring' => 'Recorrente',
    ];

    public const STATUSES = [
        'active' => 'Ativa',
        'used' => 'Utilizada',
        'expired' => 'Expirada',
        'revoked' => 'Revogada',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitorVisit::class, 'authorization_id');
    }

    /** Está válida para uso hoje? (ativa + dentro da janela de datas). */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $today = Carbon::today();

        if ($this->valid_from && $today->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $today->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
