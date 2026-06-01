<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $fillable = ['tenant_id', 'condominium_id', 'name', 'floors'];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
