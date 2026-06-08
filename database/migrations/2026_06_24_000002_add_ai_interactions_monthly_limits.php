<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $defaults = [
            'starter' => 0,
            'profissional' => 0,
            'business' => 0,
            'enterprise' => -1,
        ];

        foreach (DB::table('plans')->get(['id', 'name']) as $plan) {
            $exists = DB::table('plan_limits')
                ->where('plan_id', $plan->id)
                ->where('resource', 'ai_interactions_monthly')
                ->exists();

            if (! $exists) {
                DB::table('plan_limits')->insert([
                    'id' => (string) Str::uuid(),
                    'plan_id' => $plan->id,
                    'resource' => 'ai_interactions_monthly',
                    'limit_value' => $defaults[$plan->name] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (DB::table('tenants')->get(['id']) as $tenant) {
            $exists = DB::table('tenant_usage_counters')
                ->where('tenant_id', $tenant->id)
                ->where('resource', 'ai_interactions_monthly')
                ->exists();

            if (! $exists) {
                DB::table('tenant_usage_counters')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'resource' => 'ai_interactions_monthly',
                    'current_value' => 0,
                    'reset_at' => null,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('plan_limits')->where('resource', 'ai_interactions_monthly')->delete();
        DB::table('tenant_usage_counters')->where('resource', 'ai_interactions_monthly')->delete();
        DB::table('tenant_limits')->where('resource', 'ai_interactions_monthly')->delete();
    }
};
