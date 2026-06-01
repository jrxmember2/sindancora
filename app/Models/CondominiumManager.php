<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CondominiumManager extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'person_id', 'role', 'start_date', 'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    public static function roleLabels(): array
    {
        return [
            'sindico' => 'Síndico',
            'subsindico' => 'Subsíndico',
            'conselheiro' => 'Conselheiro',
        ];
    }
}
