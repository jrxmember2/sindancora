<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'cpf', 'email', 'phone', 'phone2',
        'birth_date', 'zip_code', 'street', 'number', 'complement',
        'neighborhood', 'city', 'state', 'notes',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function unitLinks(): HasMany
    {
        return $this->hasMany(PersonUnitLink::class);
    }

    public function activeLinks(): HasMany
    {
        return $this->hasMany(PersonUnitLink::class)->whereNull('end_date');
    }

    public function managerships(): HasMany
    {
        return $this->hasMany(CondominiumManager::class);
    }

    /** Usuário do portal vinculado a esta pessoa (se já convidada/ativada). */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function getFormattedCpfAttribute(): ?string
    {
        if (! $this->cpf) return null;
        $cpf = preg_replace('/\D/', '', $this->cpf);
        if (strlen($cpf) !== 11) return $this->cpf;
        return substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);
    }
}
