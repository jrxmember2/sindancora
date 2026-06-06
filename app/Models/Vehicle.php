<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Veículo cadastrado em uma unidade.
 */
class Vehicle extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'vehicles';

    protected $fillable = ['tenant_id', 'unit_id', 'type', 'plate', 'brand_model', 'color', 'parking_spot', 'notes'];

    public const TYPES = [
        'car' => 'Carro',
        'motorcycle' => 'Moto',
        'truck' => 'Caminhão',
        'bike' => 'Bicicleta',
        'other' => 'Outro',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
