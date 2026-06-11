<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\PlanModule;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'starter',
                'display_name' => 'Starter',
                'description' => 'Ideal para síndicos moradores e condomínios pequenos.',
                'price_monthly' => 149.00,
                'price_yearly' => 1490.00,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 1,
                'limits' => [
                    'condominiums' => 1,
                    'units' => 100,
                    'users' => 5,
                    'residents' => 100,
                    'storage_mb' => 5120,       // 5 GB
                    'announcements_monthly' => 50,
                    'emails_monthly' => 500,
                    'api_calls_monthly' => 0,    // API não disponível
                    'ai_interactions_monthly' => 0,
                    'whatsapp_connections' => 1,
                ],
                'modules' => [
                    'condominiums', 'units', 'persons', 'announcements',
                    'occurrences', 'reservations', 'documents', 'portal', 'notifications',
                    'public_links', 'polls', 'lost_found', 'disciplinary', 'community_board',
                ],
            ],
            [
                'name' => 'profissional',
                'display_name' => 'Profissional',
                'description' => 'Para síndicos profissionais com até 3 condomínios.',
                'price_monthly' => 349.00,
                'price_yearly' => 3490.00,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 2,
                'limits' => [
                    'condominiums' => 3,
                    'units' => 500,
                    'users' => 15,
                    'residents' => 500,
                    'storage_mb' => 20480,       // 20 GB
                    'announcements_monthly' => 200,
                    'emails_monthly' => 2000,
                    'api_calls_monthly' => 0,
                    'ai_interactions_monthly' => 0,
                    'whatsapp_connections' => 3,
                ],
                'modules' => [
                    'condominiums', 'units', 'persons', 'announcements',
                    'occurrences', 'reservations', 'documents', 'portal', 'notifications',
                    'financial', 'reports', 'import', 'suppliers', 'employees', 'maintenance', 'quotations', 'works', 'schedule',
                    'public_links', 'polls', 'lost_found', 'disciplinary', 'community_board',
                ],
            ],
            [
                'name' => 'business',
                'display_name' => 'Business',
                'description' => 'Para administradoras com múltiplos condomínios.',
                'price_monthly' => 749.00,
                'price_yearly' => 7490.00,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 3,
                'limits' => [
                    'condominiums' => 15,
                    'units' => 3000,
                    'users' => 60,
                    'residents' => 3000,
                    'storage_mb' => 102400,      // 100 GB
                    'announcements_monthly' => -1, // ilimitado
                    'emails_monthly' => -1,
                    'api_calls_monthly' => 100000,
                    'ai_interactions_monthly' => 0,
                    'whatsapp_connections' => 10,
                ],
                'modules' => [
                    'condominiums', 'units', 'persons', 'announcements',
                    'occurrences', 'reservations', 'documents', 'portal', 'notifications',
                    'financial', 'reports', 'import', 'api', 'webhooks',
                    'suppliers', 'employees', 'maintenance', 'quotations', 'works', 'schedule',
                    'public_links', 'polls', 'lost_found', 'disciplinary', 'community_board',
                ],
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise',
                'description' => 'Para grandes administradoras. Plano sob consulta.',
                'price_monthly' => null,
                'price_yearly' => null,
                'is_active' => true,
                'is_public' => false,
                'sort_order' => 4,
                'limits' => [
                    'condominiums' => -1,
                    'units' => -1,
                    'users' => -1,
                    'residents' => -1,
                    'storage_mb' => -1,
                    'announcements_monthly' => -1,
                    'emails_monthly' => -1,
                    'api_calls_monthly' => -1,
                    'ai_interactions_monthly' => -1,
                    'whatsapp_connections' => -1,
                ],
                'modules' => [
                    'condominiums', 'units', 'persons', 'announcements',
                    'occurrences', 'reservations', 'documents', 'portal', 'notifications',
                    'financial', 'reports', 'import', 'api', 'webhooks',
                    'whatsapp', 'ai_assistant', 'assemblies', 'gatehouse',
                    'suppliers', 'employees', 'maintenance', 'quotations', 'works', 'schedule', 'white_label',
                    'public_links', 'polls', 'lost_found', 'disciplinary', 'community_board',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $limits = $planData['limits'];
            $modules = $planData['modules'];
            unset($planData['limits'], $planData['modules']);

            $plan = Plan::updateOrCreate(['name' => $planData['name']], $planData);

            foreach ($limits as $resource => $value) {
                PlanLimit::updateOrCreate(
                    ['plan_id' => $plan->id, 'resource' => $resource],
                    ['limit_value' => $value],
                );
            }

            foreach ($modules as $module) {
                PlanModule::updateOrCreate(
                    ['plan_id' => $plan->id, 'module' => $module],
                    ['enabled' => true],
                );
            }

            PlanModule::where('plan_id', $plan->id)
                ->whereNotIn('module', $modules)
                ->update(['enabled' => false]);
        }
    }
}
