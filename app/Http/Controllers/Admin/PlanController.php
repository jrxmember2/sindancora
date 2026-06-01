<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /** Recursos limitáveis por plano (-1 = ilimitado). */
    private const RESOURCES = [
        'condominiums' => 'Condomínios',
        'units' => 'Unidades',
        'users' => 'Usuários',
        'residents' => 'Moradores',
        'storage_mb' => 'Armazenamento (MB)',
        'announcements_monthly' => 'Comunicados/mês',
        'emails_monthly' => 'E-mails/mês',
        'api_calls_monthly' => 'Chamadas de API/mês',
    ];

    /** Módulos habilitáveis por plano. */
    private const MODULES = [
        'condominiums' => 'Condomínios',
        'units' => 'Unidades',
        'persons' => 'Pessoas',
        'announcements' => 'Comunicados',
        'occurrences' => 'Ocorrências',
        'reservations' => 'Reservas',
        'documents' => 'Documentos',
        'portal' => 'Portal do Morador',
        'notifications' => 'Notificações',
        'financial' => 'Financeiro',
        'reports' => 'Relatórios',
        'import' => 'Importação CSV',
        'api' => 'API Pública',
        'webhooks' => 'Webhooks',
        'whatsapp' => 'WhatsApp',
        'ai_assistant' => 'Assistente IA',
        'assemblies' => 'Assembleias',
        'gatehouse' => 'Portaria',
        'white_label' => 'White-label',
    ];

    public function index(): Response
    {
        $plans = Plan::withCount('tenants')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'display_name', 'price_monthly', 'price_yearly', 'is_active', 'is_public', 'sort_order']);

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => null,
            'limits' => (object) [],
            'modules' => [],
            'resourceLabels' => self::RESOURCES,
            'moduleLabels' => self::MODULES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $plan = Plan::create($this->planAttributes($data));
            $this->syncLimits($plan, $data['limits'] ?? []);
            $this->syncModules($plan, $data['modules'] ?? []);
        });

        return redirect()->route('admin.plans.index')->with('success', "Plano \"{$data['display_name']}\" criado.");
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => $plan->only(['id', 'name', 'display_name', 'description', 'price_monthly', 'price_yearly', 'is_active', 'is_public', 'sort_order']),
            'limits' => $plan->limits()->pluck('limit_value', 'resource'),
            'modules' => $plan->modules()->where('enabled', true)->pluck('module'),
            'resourceLabels' => self::RESOURCES,
            'moduleLabels' => self::MODULES,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validateData($request, $plan->id);

        DB::transaction(function () use ($plan, $data) {
            $plan->update($this->planAttributes($data));
            $this->syncLimits($plan, $data['limits'] ?? []);
            $this->syncModules($plan, $data['modules'] ?? []);
        });

        return redirect()->route('admin.plans.index')->with('success', "Plano \"{$plan->display_name}\" atualizado.");
    }

    public function toggleActive(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('success', $plan->is_active ? 'Plano ativado.' : 'Plano desativado.');
    }

    private function validateData(Request $request, ?string $planId = null): array
    {
        $resources = implode(',', array_keys(self::RESOURCES));
        $modules = implode(',', array_keys(self::MODULES));

        return $request->validate([
            'name' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('plans', 'name')->ignore($planId)],
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'limits' => ['array', function ($attr, $value, $fail) use ($resources) {
                foreach (array_keys($value ?? []) as $key) {
                    if (! in_array($key, explode(',', $resources), true)) {
                        $fail("Recurso de limite inválido: {$key}.");
                    }
                }
            }],
            'limits.*' => 'integer|min:-1',
            'modules' => 'array',
            'modules.*' => "string|in:{$modules}",
        ]);
    }

    private function planAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'price_monthly' => $data['price_monthly'] ?? null,
            'price_yearly' => $data['price_yearly'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_public' => $data['is_public'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function syncLimits(Plan $plan, array $limits): void
    {
        foreach (self::RESOURCES as $resource => $label) {
            $value = array_key_exists($resource, $limits) ? (int) $limits[$resource] : 0;
            $plan->limits()->updateOrCreate(
                ['resource' => $resource],
                ['limit_value' => $value],
            );
        }
    }

    private function syncModules(Plan $plan, array $enabledModules): void
    {
        foreach (self::MODULES as $module => $label) {
            $plan->modules()->updateOrCreate(
                ['module' => $module],
                ['enabled' => in_array($module, $enabledModules, true)],
            );
        }
    }
}
