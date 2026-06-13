<?php

namespace App\Http\Controllers;

use App\Models\BillingPayment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela "Assinatura em atraso" do tenant bloqueado. Acessível mesmo com a conta suspensa
 * (liberada no ResolveTenant) para que o cliente regularize pela fatura.
 */
class BillingBlockController extends Controller
{
    public function show(): RedirectResponse|Response
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        // Conta ativa caindo aqui por engano → volta para o sistema.
        if (! $tenant || ! $tenant->isSuspended()) {
            return redirect('/');
        }

        $subscription = $tenant->billingSubscription()->with('plan')->first();

        $invoiceUrl = $subscription
            ? BillingPayment::where('billing_subscription_id', $subscription->id)
                ->whereNotIn('status', BillingPayment::PAID_STATUSES)
                ->orderByDesc('due_date')
                ->value('invoice_url')
            : null;

        return Inertia::render('Billing/Suspended', [
            'tenantName' => $tenant->name,
            'plan' => $subscription?->plan?->display_name,
            'value' => $subscription?->value,
            'invoiceUrl' => $invoiceUrl,
        ]);
    }
}
