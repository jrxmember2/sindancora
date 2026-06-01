<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasPermission('users:view') || $authUser->is_super_admin;
    }

    public function view(User $authUser, User $user): bool
    {
        if ($authUser->is_super_admin) {
            return true;
        }
        return $authUser->tenant_id === $user->tenant_id && $authUser->hasPermission('users:view');
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasPermission('users:create') || $authUser->is_super_admin;
    }

    public function update(User $authUser, User $user): bool
    {
        if ($authUser->is_super_admin) {
            return true;
        }
        return $authUser->tenant_id === $user->tenant_id && $authUser->hasPermission('users:edit');
    }

    public function delete(User $authUser, User $user): bool
    {
        if ($authUser->is_super_admin) {
            return true;
        }
        if ($authUser->id === $user->id) {
            return false;
        }
        return $authUser->tenant_id === $user->tenant_id && $authUser->hasPermission('users:delete');
    }
}
