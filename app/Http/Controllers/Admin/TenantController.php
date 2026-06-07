<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Rules\CpfCnpj;
use App\Services\PlanLimitService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly PlanLimitService $planLimitService,
    ) {}

    public function index(Request $request): Response
    {
        $tenants = Tenant::withCount('users')
            ->with(['plan', 'domains' => fn ($q) => $q->where('type', 'subdomain')])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tenants/Create', [
            'plans' => Plan::active()->orderBy('sort_order')->get(['id', 'name', 'display_name', 'price_monthly']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['document' => preg_replace('/\D/', '', (string) $request->input('document')) ?: null]);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:50|alpha_dash|unique:tenants,slug',
            'document' => ['nullable', 'string', 'max:18', new CpfCnpj],
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'plan_id' => 'required|uuid|exists:plans,id',
            'admin_name' => 'required|string|max:100',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $tenant = $this->tenantService->create($data, $plan);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', "Tenant \"{$tenant->name}\" criado com sucesso.");
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load([
            'plan',
            'domains',
            'usageCounters',
            'users' => fn ($q) => $q->with('userRoles.role')->latest()->limit(10),
        ]);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant,
            'plans' => Plan::active()->orderBy('sort_order')->get(['id', 'name', 'display_name']),
        ]);
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspended']);
        $this->forgetTenantCache($tenant);

        return back()->with('success', "Tenant \"{$tenant->name}\" suspenso.");
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'active']);
        $this->forgetTenantCache($tenant);

        return back()->with('success', "Tenant \"{$tenant->name}\" reativado.");
    }

    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|uuid|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $tenant->update(['plan_id' => $plan->id]);

        if ($subscription = $tenant->activeSubscription()->first()) {
            $subscription->update(['plan_id' => $plan->id]);
        } else {
            $tenant->activeSubscription()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
            ]);
        }

        $this->forgetTenantCache($tenant);
        $this->planLimitService->syncPermanentCounters($tenant);

        return back()->with('success', "Plano alterado para \"{$plan->display_name}\".");
    }

    private function forgetTenantCache(Tenant $tenant): void
    {
        foreach ($tenant->domains()->pluck('domain') as $domain) {
            Cache::forget("tenant:domain:{$domain}");
        }
    }
}
