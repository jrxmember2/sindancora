<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonUnitLink extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $fillable = [
        'tenant_id', 'person_id', 'unit_id', 'type', 'is_primary', 'start_date', 'end_date',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    public static function typeLabels(): array
    {
        return [
            'owner' => 'Proprietário',
            'tenant' => 'Locatário',
            'resident' => 'Morador',
            'dependent' => 'Dependente',
        ];
    }
}
