<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorVisit extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'visitor_visits';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'unit_id', 'authorization_id',
        'visitor_name', 'visitor_document', 'check_in_at', 'check_out_at',
        'registered_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
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

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(VisitorAuthorization::class, 'authorization_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** Visita em aberto (visitante ainda dentro do condomínio). */
    public function scopePresent($query)
    {
        return $query->whereNull('check_out_at');
    }
}
