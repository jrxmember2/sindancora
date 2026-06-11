<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enquete rápida por condomínio (1 voto por pessoa). Diferente da Assembleia, é uma consulta leve,
 * não deliberativa. Estados: draft → open → closed.
 */
class Poll extends Model
{
    use BelongsToTenant, HasUuidKey;

    public const STATUSES = [
        'draft' => 'Rascunho',
        'open' => 'Aberta',
        'closed' => 'Encerrada',
    ];

    protected $fillable = [
        'tenant_id', 'condominium_id', 'title', 'description',
        'status', 'is_anonymous', 'closes_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'closes_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
