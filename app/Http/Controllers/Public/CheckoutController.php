<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Rules\CpfCnpj;
use App\Services\Billing\BillingService;
use App\Services\Payments\AsaasException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fluxo público de contratação: vitrine de planos → checkout no Asaas → tela de pagamento.
 * Nenhum tenant é criado aqui; o provisionamento é disparado pelo webhook após a compensação.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function plans(): Response
    {
        return Inertia::render('Public/Plans', [
            'plans' => Plan::active()->public()->orderBy('sort_order')->get([
                'id', 'name', 'display_name', 'description', 'price_monthly', 'price_yearly',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['document' => preg_replace('/\D/', '', (string) $request->input('document')) ?: null]);

        $data = $request->validate([
            'plan_id' => 'required|uuid|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'billing_type' => 'required|in:PIX,CREDIT_CARD,BOLETO',
            'company_name' => 'required|string|max:150',
            'document' => ['required', 'string', 'max:18', new CpfCnpj],
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'admin_name' => 'required|string|max:150',
        ]);

        try {
            ['signup' => $signup] = $this->billing->startCheckout($data);
        } catch (AsaasException $e) {
            return back()->withInput()->with('error', 'Não foi possível iniciar o pagamento: '.$e->getMessage());
        }

        return redirect()->route('checkout.pending', $signup);
    }

    public function pending(PendingSignup $signup): RedirectResponse|Response
    {
        if ($signup->isProvisioned() && $signup->tenant) {
            $domain = $signup->tenant->domains()->where('active', true)->value('domain');

            return redirect()->away($domain ? "https://{$domain}/login" : '/login')
                ->with('success', 'Conta criada! Verifique seu e-mail para o primeiro acesso.');
        }

        // Re-resolve a 1ª cobrança (mantém QR/links atualizados a cada carregamento).
        $payment = [];
        try {
            $payment = $this->billing->resolveFirstPayment($signup);
        } catch (\Throwable) {
            // Falha transitória do gateway não derruba a tela.
        }

        return Inertia::render('Public/CheckoutPending', [
            'signup' => $signup->only(['id', 'company_name', 'email', 'billing_type', 'billing_cycle', 'value', 'status']),
            'plan' => $signup->plan?->only(['display_name']),
            'payment' => $payment,
        ]);
    }

    public function status(PendingSignup $signup): JsonResponse
    {
        $loginUrl = null;
        if ($signup->isProvisioned() && $signup->tenant) {
            $domain = $signup->tenant->domains()->where('active', true)->value('domain');
            $loginUrl = $domain ? "https://{$domain}/login" : '/login';
        }

        return response()->json([
            'status' => $signup->status,
            'provisioned' => $signup->isProvisioned(),
            'login_url' => $loginUrl,
        ]);
    }
}
