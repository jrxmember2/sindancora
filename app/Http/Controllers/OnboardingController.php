<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(private readonly TenantService $tenantService) {}

    public function create(): Response
    {
        return Inertia::render('Onboarding/Register', [
            'plans' => Plan::active()->public()->orderBy('sort_order')->get([
                'id', 'name', 'display_name', 'description', 'price_monthly',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:100',
            'document' => 'nullable|string|max:18',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'plan_id' => 'required|uuid|exists:plans,id',
            'admin_name' => 'required|string|max:100',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $tenant = $this->tenantService->create([
            'name' => $data['company_name'],
            'document' => $data['document'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'admin_name' => $data['admin_name'],
            'admin_email' => $data['admin_email'],
            'admin_password' => $data['admin_password'],
        ], $plan);

        $domain = $tenant->domains()->where('type', 'subdomain')->first();

        return redirect()->away("http://{$domain->domain}/login")
            ->with('success', 'Conta criada com sucesso! Faça login para continuar.');
    }
}
