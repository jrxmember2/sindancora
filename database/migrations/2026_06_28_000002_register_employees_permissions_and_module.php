<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module' => 'employees', 'action' => 'create', 'name' => 'employees:create', 'description' => 'Cadastrar funcionarios'],
            ['module' => 'employees', 'action' => 'read', 'name' => 'employees:read', 'description' => 'Visualizar funcionarios e ferias'],
            ['module' => 'employees', 'action' => 'update', 'name' => 'employees:update', 'description' => 'Editar funcionarios e periodos de ferias'],
            ['module' => 'employees', 'action' => 'delete', 'name' => 'employees:delete', 'description' => 'Remover funcionarios e periodos de ferias'],
        ];

        $permissionIds = [];

        foreach ($permissions as $permission) {
            $existing = DB::table('permissions')->where('name', $permission['name'])->first();

            if ($existing) {
                DB::table('permissions')->where('id', $existing->id)->update([
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                    'description' => $permission['description'],
                ]);
                $permissionIds[$permission['name']] = $existing->id;

                continue;
            }

            $id = (string) Str::uuid();
            DB::table('permissions')->insert(array_merge($permission, ['id' => $id]));
            $permissionIds[$permission['name']] = $id;
        }

        $rolePermissions = [
            'admin' => array_keys($permissionIds),
            'sindico' => array_keys($permissionIds),
            'subsindico' => ['employees:read'],
            'conselheiro' => ['employees:read'],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $role = DB::table('roles')->whereNull('tenant_id')->where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            foreach ($names as $name) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionIds[$name],
                ]);
            }
        }

        foreach (['profissional', 'business', 'enterprise'] as $planName) {
            $plan = DB::table('plans')->where('name', $planName)->first();

            if (! $plan) {
                continue;
            }

            $module = DB::table('plan_modules')
                ->where('plan_id', $plan->id)
                ->where('module', 'employees')
                ->first();

            if ($module) {
                DB::table('plan_modules')->where('id', $module->id)->update(['enabled' => true]);
            } else {
                DB::table('plan_modules')->insert([
                    'id' => (string) Str::uuid(),
                    'plan_id' => $plan->id,
                    'module' => 'employees',
                    'enabled' => true,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('module', 'employees')
            ->pluck('id')
            ->all();

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('plan_modules')->where('module', 'employees')->delete();
        DB::table('permissions')->where('module', 'employees')->delete();
    }
};
