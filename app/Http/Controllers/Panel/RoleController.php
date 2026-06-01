<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        $roles = Role::forTenant($tenant->id)
            ->with(['permissions' => fn ($q) => $q->orderBy('module')->orderBy('action')])
            ->orderBy('display_name')
            ->get();

        $grouped = $roles->map(function (Role $role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'tenant_id' => $role->tenant_id,
                'permission_ids' => $role->permissions->pluck('id')->values(),
                'permissions_by_module' => $role->permissions->groupBy('module')->map(fn ($perms) => $perms->pluck('action'))->toArray(),
                'permissions_count' => $role->permissions->count(),
            ];
        });

        $allPermissions = Permission::orderBy('module')->orderBy('action')
            ->get()
            ->groupBy('module')
            ->map(fn ($perms) => $perms->map(fn ($p) => [
                'id' => $p->id,
                'action' => $p->action,
                'name' => $p->name,
            ])->values())
            ->toArray();

        return Inertia::render('Roles/Index', [
            'roles' => $grouped,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $tenant = app('tenant');

        abort_if($role->is_system, 403, 'Perfis do sistema não podem ser editados.');
        abort_unless($role->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'display_name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'uuid|exists:permissions,id',
        ]);

        $role->update([
            'display_name' => $data['display_name'] ?? $role->display_name,
            'description' => $data['description'] ?? $role->description,
        ]);

        $role->permissions()->sync($data['permission_ids']);

        return redirect()->route('roles.index')->with('success', "Perfil \"{$role->display_name}\" atualizado.");
    }
}
