<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assembly extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'title', 'description', 'scheduled_at',
        'status', 'minutes', 'minutes_generated_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'minutes_generated_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'draft' => 'Rascunho',
        'open' => 'Votação aberta',
        'closed' => 'Encerrada',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssemblyAgendaItem::class)->orderBy('position');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(AssemblyAttendance::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
