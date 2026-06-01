<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::where('name', 'business')->first();
        if (! $plan) {
            return;
        }

        $tenant = Tenant::updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Condomínio Demo',
                'slug' => 'demo',
                'email' => env('DEMO_TENANT_EMAIL', 'demo@sindancora.com.br'),
                'status' => 'active',
                'plan_id' => $plan->id,
            ]
        );

        // Domínio principal extraído de APP_URL (ex: app.sindancora.site)
        $appUrl = config('app.url');
        $appDomain = parse_url($appUrl, PHP_URL_HOST) ?? $appUrl;
        // Remove porta se houver
        $appDomain = explode(':', $appDomain)[0];

        if ($appDomain) {
            TenantDomain::updateOrCreate(
                ['tenant_id' => $tenant->id, 'domain' => $appDomain],
                ['active' => true]
            );
        }

        // Usuário admin do tenant demo
        if (! User::where('email', env('DEMO_ADMIN_EMAIL', 'admin@demo.com'))->exists()) {
            User::create([
                'name' => 'Admin Demo',
                'email' => env('DEMO_ADMIN_EMAIL', 'admin@demo.com'),
                'password' => Hash::make(env('DEMO_ADMIN_PASSWORD', 'Demo@2026!')),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }
    }
}
