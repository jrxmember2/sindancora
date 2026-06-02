<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasAuditLog, HasFactory, HasUuidKey, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'person_id', 'name', 'email', 'phone', 'document',
        'password', 'status', 'is_super_admin', 'last_login_at',
    ];

    /** Papéis com acesso ao painel administrativo (todos exceto o morador). */
    public const PANEL_ROLES = ['admin', 'sindico', 'subsindico', 'conselheiro'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function roles()
    {
        return Role::whereIn('id', $this->userRoles()->pluck('role_id'));
    }

    public function hasRole(string $roleName): bool
    {
        if ($this->is_super_admin) {
            return true;
        }
        return $this->userRoles()->whereHas('role', fn ($q) => $q->where('name', $roleName))->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return in_array($permission, $this->permissionNames(), true);
    }

    /**
     * Lista de nomes de permissão do usuário (ex: ['users:read', ...]).
     * Super admin recebe ['*'] (acesso total).
     */
    public function permissionNames(): array
    {
        if ($this->is_super_admin) {
            return ['*'];
        }

        return $this->userRoles()
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role?->permissions ?? collect())
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Pode acessar o painel administrativo? (super admin ou qualquer papel de gestão).
     * Moradores "puros" (só role morador) ficam restritos ao portal.
     */
    public function canAccessPanel(): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->userRoles()
            ->whereHas('role', fn ($q) => $q->whereIn('name', self::PANEL_ROLES))
            ->exists();
    }

    /** É morador do portal? (tem papel morador ou está vinculado a uma Person). */
    public function isResident(): bool
    {
        return $this->hasRole('morador') || $this->person_id !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Número para o canal WhatsApp (Evolution API): dígitos com DDI.
     * Usa o telefone da Pessoa vinculada (morador) ou o do próprio usuário.
     */
    public function routeNotificationForWhatsapp(): ?string
    {
        $raw = $this->person?->phone ?: $this->phone;
        $digits = preg_replace('/\D/', '', (string) $raw);

        if (blank($digits)) {
            return null;
        }

        // Telefone BR sem DDI (10–11 dígitos) → prefixa 55.
        if (strlen($digits) <= 11) {
            $digits = '55'.$digits;
        }

        return $digits;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
