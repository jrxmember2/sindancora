<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pet cadastrado em uma unidade.
 */
class Pet extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'pets';

    protected $fillable = ['tenant_id', 'unit_id', 'name', 'species', 'breed', 'notes'];

    public const SPECIES = [
        'dog' => 'Cachorro',
        'cat' => 'Gato',
        'bird' => 'Pássaro',
        'fish' => 'Peixe',
        'rodent' => 'Roedor',
        'reptile' => 'Réptil',
        'other' => 'Outro',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
