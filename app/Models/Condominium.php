<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Condominium extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $table = 'condominiums';

    protected $fillable = [
        'tenant_id', 'name', 'cnpj', 'email', 'phone',
        'zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
        'settings', 'status',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function managers(): HasMany
    {
        return $this->hasMany(CondominiumManager::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function activeManagers(): HasMany
    {
        return $this->hasMany(CondominiumManager::class)->whereNull('end_date');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->street, $this->number, $this->complement, $this->neighborhood, $this->city, $this->state])
            ->filter()
            ->implode(', ');
    }
}
