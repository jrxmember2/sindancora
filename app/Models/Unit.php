<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'block_id',
        'number', 'floor', 'type', 'area_m2', 'fraction', 'status',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(PersonUnitLink::class);
    }

    public function activeLinks(): HasMany
    {
        return $this->hasMany(PersonUnitLink::class)->whereNull('end_date');
    }

    public function scopeOfCondominium($query, string $condominiumId)
    {
        return $query->where('condominium_id', $condominiumId);
    }

    public static function typeLabels(): array
    {
        return [
            'apartment' => 'Apartamento',
            'house' => 'Casa',
            'commercial' => 'Comercial',
            'garage' => 'Garagem',
            'storage' => 'Depósito',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'occupied' => 'Ocupada',
            'vacant' => 'Vaga',
            'under_renovation' => 'Em Obras',
        ];
    }
}
