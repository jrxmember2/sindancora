<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            DemoTenantSeeder::class,
        ]);

        // Super Admin inicial
        if (! User::where('is_super_admin', true)->exists()) {
            User::create([
                'name' => 'Super Admin',
                'email' => env('SUPER_ADMIN_EMAIL', 'admin@sindancora.com.br'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'SindAncora@2026!')),
                'is_super_admin' => true,
                'status' => 'active',
                'tenant_id' => null,
            ]);
        }
    }
}
