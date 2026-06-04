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

class CommonArea extends Model
{
    use BelongsToTenant, HasAttachments, HasAuditLog, HasUuidKey, SoftDeletes;

    public const ATTACHMENT_ENTITY = 'common_area';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'name', 'description', 'capacity',
        'requires_approval', 'min_advance_days', 'opening_time', 'closing_time',
        'fee', 'deposit', 'rules', 'active',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'active' => 'boolean',
            'capacity' => 'integer',
            'min_advance_days' => 'integer',
            'fee' => 'decimal:2',
            'deposit' => 'decimal:2',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
