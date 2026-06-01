<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        if ($user->is_super_admin) {
            return true;
        }
        return $user->tenant_id === $tenant->id;
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->is_super_admin) {
            return true;
        }
        return $user->tenant_id === $tenant->id && $user->hasPermission('settings:edit');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin;
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin;
    }
}
