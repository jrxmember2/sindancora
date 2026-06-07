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

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_COMPANY = 'company';

    protected $table = 'persons';

    protected $fillable = [
        'tenant_id', 'person_type', 'name', 'cpf', 'email', 'phone', 'phone2', 'phones', 'emails',
        'birth_date', 'zip_code', 'street', 'number', 'complement',
        'neighborhood', 'city', 'state', 'notes', 'gateway_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'phones' => 'array',
            'emails' => 'array',
        ];
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
        $document = preg_replace('/\D/', '', $this->cpf);
        if (strlen($document) === 11) {
            return substr($document, 0, 3).'.'.substr($document, 3, 3).'.'.substr($document, 6, 3).'-'.substr($document, 9, 2);
        }
        if (strlen($document) === 14) {
            return substr($document, 0, 2).'.'.substr($document, 2, 3).'.'.substr($document, 5, 3).'/'.substr($document, 8, 4).'-'.substr($document, 12, 2);
        }

        return $this->cpf;
    }
}
