<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPlanSubscription;
use App\Models\TenantUsageCounter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantService
{
    public function create(array $data, Plan $plan): Tenant
    {
        return DB::transaction(function () use ($data, $plan) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->generateSlug($data['name']),
                'document' => $data['document'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
                'plan_id' => $plan->id,
            ]);

            // Domínio padrão (subdomínio)
            $appDomain = config('sindancora.domain', 'sindancora.com.br');
            TenantDomain::create([
                'tenant_id' => $tenant->id,
                'domain' => "{$tenant->slug}.{$appDomain}",
                'type' => 'subdomain',
                'active' => true,
            ]);

            // Assinatura do plano
            TenantPlanSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
            ]);

            // Contadores zerados
            $this->initializeUsageCounters($tenant);

            // Usuário admin inicial (se informado)
            if (isset($data['admin_name'], $data['admin_email'], $data['admin_password'])) {
                $this->createAdminUser($tenant, $data);
            }

            return $tenant;
        });
    }

    public function createAdminUser(Tenant $tenant, array $data): User
    {
        $adminRole = Role::where('name', 'admin')->whereNull('tenant_id')->first();

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'status' => 'active',
        ]);

        if ($adminRole) {
            $user->userRoles()->create(['role_id' => $adminRole->id]);
        }

        return $user;
    }

    private function generateSlug(string $name): string
    {
        $base = Str::slug($name, '-');
        $slug = $base;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function initializeUsageCounters(Tenant $tenant): void
    {
        $resources = [
            'condominiums', 'units', 'users', 'residents',
            'storage_mb', 'announcements_monthly', 'emails_monthly', 'api_calls_monthly',
        ];

        foreach ($resources as $resource) {
            TenantUsageCounter::create([
                'tenant_id' => $tenant->id,
                'resource' => $resource,
                'current_value' => 0,
            ]);
        }
    }
}
