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
        'tenant_id', 'name', 'email', 'phone', 'document',
        'password', 'status', 'is_super_admin', 'last_login_at',
    ];

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

        return $this->userRoles()
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions ?? collect())
            ->pluck('name')
            ->contains($permission);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
