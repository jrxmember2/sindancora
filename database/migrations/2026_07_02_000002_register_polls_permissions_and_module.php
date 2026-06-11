<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module' => 'polls', 'action' => 'read', 'name' => 'polls:read', 'description' => 'Ver enquetes e resultados'],
            ['module' => 'polls', 'action' => 'manage', 'name' => 'polls:manage', 'description' => 'Criar, abrir, encerrar e excluir enquetes'],
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
            'subsindico' => ['polls:read', 'polls:manage'],
            'conselheiro' => ['polls:read'],
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

        // Feature de engajamento: habilitada em todos os planos.
        foreach (['starter', 'profissional', 'business', 'enterprise'] as $planName) {
            $plan = DB::table('plans')->where('name', $planName)->first();

            if (! $plan) {
                continue;
            }

            $module = DB::table('plan_modules')
                ->where('plan_id', $plan->id)
                ->where('module', 'polls')
                ->first();

            if ($module) {
                DB::table('plan_modules')->where('id', $module->id)->update(['enabled' => true]);
            } else {
                DB::table('plan_modules')->insert([
                    'id' => (string) Str::uuid(),
                    'plan_id' => $plan->id,
                    'module' => 'polls',
                    'enabled' => true,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->where('module', 'polls')->pluck('id')->all();

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('plan_modules')->where('module', 'polls')->delete();
        DB::table('permissions')->where('module', 'polls')->delete();
    }
};
