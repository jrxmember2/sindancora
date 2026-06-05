<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    private array $roles = [
        'super_admin' => [
            'display_name' => 'Super Admin',
            'description' => 'Acesso total ao sistema SaaS. Gerencia todos os tenants.',
            'permissions' => '*', // todas as permissões
        ],
        'admin' => [
            'display_name' => 'Administrador',
            'description' => 'Administrador do tenant. Acesso total ao tenant.',
            'permissions' => [
                'condominiums:create', 'condominiums:read', 'condominiums:update', 'condominiums:delete',
                'units:create', 'units:read', 'units:update', 'units:delete', 'units:import',
                'persons:create', 'persons:read', 'persons:update', 'persons:delete', 'persons:link',
                'announcements:create', 'announcements:read', 'announcements:update', 'announcements:delete', 'announcements:publish',
                'occurrences:create', 'occurrences:read', 'occurrences:update', 'occurrences:close', 'occurrences:delete',
                'reservations:create', 'reservations:read', 'reservations:approve', 'reservations:reject', 'reservations:cancel',
                'documents:upload', 'documents:read', 'documents:download', 'documents:delete',
                'charges:create', 'charges:read', 'charges:update', 'charges:delete', 'charges:mark_paid',
                'expenses:create', 'expenses:read', 'expenses:update', 'expenses:delete',
                'reports:read', 'reports:export',
                'assemblies:create', 'assemblies:read', 'assemblies:update', 'assemblies:delete',
                'users:create', 'users:read', 'users:update', 'users:delete', 'users:manage',
                'settings:read', 'settings:update', 'settings:payments', 'settings:whatsapp',
                'api_keys:manage', 'webhooks:manage', 'ai:use',
                'gatehouse:read', 'gatehouse:register', 'gatehouse:manage',
                'inbox:use', 'sectors:manage',
                'audit:read',
            ],
        ],
        'sindico' => [
            'display_name' => 'Síndico',
            'description' => 'Síndico eleito. Acesso operacional ao condomínio.',
            'permissions' => [
                'condominiums:read',
                'units:create', 'units:read', 'units:update', 'units:import',
                'persons:create', 'persons:read', 'persons:update', 'persons:link',
                'announcements:create', 'announcements:read', 'announcements:update', 'announcements:publish',
                'occurrences:create', 'occurrences:read', 'occurrences:update', 'occurrences:close',
                'reservations:create', 'reservations:read', 'reservations:approve', 'reservations:reject', 'reservations:cancel',
                'documents:upload', 'documents:read', 'documents:download', 'documents:delete',
                'charges:create', 'charges:read', 'charges:update', 'charges:mark_paid',
                'expenses:create', 'expenses:read', 'expenses:update',
                'reports:read', 'reports:export',
                'assemblies:create', 'assemblies:read', 'assemblies:update', 'assemblies:delete',
                'gatehouse:read', 'gatehouse:register', 'gatehouse:manage',
                'inbox:use', 'sectors:manage',
                'ai:use',
            ],
        ],
        'subsindico' => [
            'display_name' => 'Subsíndico',
            'description' => 'Subsíndico. Acesso de leitura amplo com algumas ações limitadas.',
            'permissions' => [
                'condominiums:read', 'units:read', 'persons:read',
                'announcements:read', 'occurrences:read', 'occurrences:update',
                'reservations:read', 'documents:read', 'documents:download',
                'gatehouse:read',
            ],
        ],
        'conselheiro' => [
            'display_name' => 'Conselheiro',
            'description' => 'Membro do conselho. Acesso de leitura.',
            'permissions' => [
                'condominiums:read', 'units:read',
                'announcements:read', 'occurrences:read',
                'reservations:read', 'documents:read', 'documents:download',
                'reports:read', 'gatehouse:read',
            ],
        ],
        'morador' => [
            'display_name' => 'Morador',
            'description' => 'Condômino. Acesso apenas ao portal do morador.',
            'permissions' => [
                'announcements:read',
                'occurrences:create', 'occurrences:read',
                'reservations:create', 'reservations:read', 'reservations:cancel',
                'documents:read', 'documents:download',
            ],
        ],
        'porteiro' => [
            'display_name' => 'Porteiro',
            'description' => 'Equipe de portaria. Acesso restrito à portaria (registrar acessos e validar QR).',
            'permissions' => [
                'gatehouse:read', 'gatehouse:register',
            ],
        ],
    ];

    public function run(): void
    {
        $allPermissions = Permission::all()->keyBy('name');

        foreach ($this->roles as $name => $config) {
            $role = Role::updateOrCreate(
                ['name' => $name, 'tenant_id' => null],
                [
                    'display_name' => $config['display_name'],
                    'description' => $config['description'],
                    'is_system' => true,
                ],
            );

            if ($config['permissions'] === '*') {
                $role->permissions()->sync($allPermissions->pluck('id'));
            } else {
                $permIds = $allPermissions->whereIn('name', $config['permissions'])->pluck('id');
                $role->permissions()->sync($permIds);
            }
        }
    }
}
