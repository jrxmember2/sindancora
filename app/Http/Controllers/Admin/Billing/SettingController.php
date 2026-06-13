<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Services\Billing\AsaasBillingClient;
use App\Services\Payments\AsaasException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurações do billing SaaS: régua de cobrança, desbloqueio por confiança e parâmetros
 * fiscais da NFS-e (Asaas /invoices).
 */
class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Billing/Settings', [
            'settings' => BillingSetting::current(),
            'gateway' => [
                'configured' => app(AsaasBillingClient::class)->isConfigured(),
                'environment' => config('services.asaas_billing.environment'),
                'webhook_url' => url('/api/webhooks/asaas/saas'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reminder_days_before' => 'required|integer|min:0|max:30',
            'overdue_day_1' => 'required|integer|min:0|max:60',
            'overdue_day_2' => 'required|integer|min:0|max:90',
            'overdue_day_3' => 'required|integer|min:0|max:120',
            'suspend_day' => 'required|integer|min:1|max:180',
            'trust_unlock_enabled' => 'boolean',
            'trust_min_months' => 'required|integer|min:0|max:60',
            'trust_tolerance_days' => 'required|integer|min:0|max:30',
            'trust_cooldown_months' => 'required|integer|min:0|max:60',
            'trust_grace_days' => 'required|integer|min:1|max:60',
            'nfse_enabled' => 'boolean',
            'nfse_service_description' => 'nullable|string|max:500',
            'nfse_municipal_service_code' => 'nullable|string|max:30',
            'nfse_iss_tax' => 'nullable|numeric|min:0|max:100',
            'nfse_deductions' => 'nullable|numeric|min:0',
            'nfse_observations' => 'nullable|string|max:1000',
            'nfse_send_email_to_customer' => 'boolean',
        ]);

        BillingSetting::current()->update($data);

        return back()->with('success', 'Configurações de cobrança atualizadas.');
    }

    public function testConnection(): JsonResponse
    {
        try {
            $account = app(AsaasBillingClient::class)->myAccount();

            return response()->json([
                'ok' => true,
                'name' => $account['name'] ?? $account['company'] ?? 'Conta Asaas',
            ]);
        } catch (AsaasException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
