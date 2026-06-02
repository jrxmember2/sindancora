<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey;

    protected $fillable = [
        'tenant_id', 'name', 'key_hash', 'key_prefix', 'scopes',
        'expires_at', 'last_used_at', 'created_by', 'revoked_at',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Catálogo de escopos disponíveis. Fonte da verdade para a UI, o seeder de api_key_scopes
     * e a documentação. Cada recurso expõe :read e :write.
     */
    public const SCOPES = [
        'condominiums:read'  => 'Listar e visualizar condomínios',
        'condominiums:write' => 'Criar e editar condomínios',
        'units:read'         => 'Listar e visualizar unidades',
        'units:write'        => 'Criar e editar unidades',
        'persons:read'       => 'Listar e visualizar pessoas',
        'persons:write'      => 'Criar e editar pessoas',
        'charges:read'       => 'Listar e visualizar cobranças',
        'charges:write'      => 'Criar cobranças',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Chave utilizável: não revogada e não expirada. */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** True se a chave possui o escopo (ou o curinga `*`). */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }

    /** Gera uma chave em claro nova (exibida ao usuário uma única vez). */
    public static function generatePlaintext(): string
    {
        return 'sk_live_'.Str::random(40);
    }

    /** Hash determinístico para armazenamento/lookup (a chave em claro nunca é persistida). */
    public static function hashKey(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }
}
