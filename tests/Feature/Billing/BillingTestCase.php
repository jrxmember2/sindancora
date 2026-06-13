<?php

namespace Tests\Feature\Billing;

use App\Models\BillingSetting;
use App\Models\BillingSubscription;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base de testes do billing SaaS. Em vez de rodar as 94 migrations (várias usam recursos
 * exclusivos do Postgres — RLS/jsonb), monta apenas o schema necessário no SQLite em memória,
 * reusando as próprias migrations de billing.
 */
abstract class BillingTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dependências mínimas (plans/tenants/tenant_domains) referenciadas pelo billing.
        Schema::create('plans', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_yearly', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenants', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->uuid('plan_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_domains', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('domain');
            $table->string('type')->default('subdomain');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Reusa as migrations reais de billing (sqlite-safe).
        foreach ([
            '2026_07_10_000001_create_billing_tables.php',
            '2026_07_10_000002_create_billing_settings_table.php',
        ] as $file) {
            (require database_path("migrations/{$file}"))->up();
        }
    }

    protected function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'pro', 'display_name' => 'Profissional',
            'price_monthly' => 199.90, 'price_yearly' => 1999.00,
            'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ], $attrs));
    }

    protected function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::create(array_merge([
            'name' => 'Condomínio Teste', 'slug' => 'cond-'.Str::random(5),
            'email' => 'sindico@teste.com', 'status' => 'active',
        ], $attrs));

        $tenant->domains()->create([
            'id' => (string) Str::uuid(),
            'domain' => $tenant->slug.'.sindancora.test',
            'type' => 'subdomain', 'active' => true,
        ]);

        return $tenant;
    }

    protected function makeSubscription(Tenant $tenant, Plan $plan, array $attrs = []): BillingSubscription
    {
        return BillingSubscription::create(array_merge([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'asaas_subscription_id' => 'sub_'.Str::random(8),
            'billing_cycle' => 'monthly', 'billing_type' => 'PIX',
            'value' => 199.90, 'status' => BillingSubscription::STATUS_ACTIVE,
            'started_at' => now()->subYear(),
        ], $attrs));
    }

    protected function settings(): BillingSetting
    {
        return BillingSetting::current();
    }
}
