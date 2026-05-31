<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'name', 'display_name', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id')
            ->withPivot('id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId));
    }
}
