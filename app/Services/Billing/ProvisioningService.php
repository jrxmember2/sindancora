<?php

namespace App\Services\Billing;

use App\Mail\Billing\TenantWelcomeMail;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Provisiona o tenant a partir de um pré-cadastro pago. Reusa o TenantService (mesma lógica do
 * super admin) e gera o primeiro acesso: link mágico assinado + senha temporária (fallback).
 * Idempotente: um signup já provisionado não recria o tenant.
 */
class ProvisioningService
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly BillingService $billing,
    ) {}

    public function provision(PendingSignup $signup): Tenant
    {
        if ($signup->isProvisioned() && $signup->tenant_id) {
            return Tenant::findOrFail($signup->tenant_id);
        }

        $plan = Plan::findOrFail($signup->plan_id);
        $tempPassword = Str::password(12);

        [$tenant, $user, $subscription] = DB::transaction(function () use ($signup, $plan, $tempPassword) {
            $tenant = $this->tenantService->create([
                'name' => $signup->company_name,
                'document' => $signup->document,
                'email' => $signup->email,
                'phone' => $signup->phone,
                'admin_name' => $signup->admin_name,
                'admin_email' => $signup->email,
                'admin_password' => $tempPassword,
            ], $plan);

            $user = $tenant->users()->where('email', $signup->email)->firstOrFail();

            $subscription = BillingSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'asaas_customer_id' => $signup->asaas_customer_id,
                'asaas_subscription_id' => $signup->asaas_subscription_id,
                'billing_cycle' => $signup->billing_cycle,
                'billing_type' => $signup->billing_type,
                'value' => $signup->value,
                'status' => BillingSubscription::STATUS_ACTIVE,
                'started_at' => now(),
                'next_due_date' => Carbon::today()->addMonth()->toDateString(),
            ]);

            // Reaponta o 1º pagamento (já espelhado) para o tenant/assinatura.
            if ($signup->first_payment_id) {
                BillingPayment::where('asaas_payment_id', $signup->first_payment_id)->update([
                    'tenant_id' => $tenant->id,
                    'billing_subscription_id' => $subscription->id,
                ]);
            }

            $signup->update([
                'status' => 'provisioned',
                'tenant_id' => $tenant->id,
                'provisioned_at' => now(),
                'error' => null,
            ]);

            return [$tenant, $user, $subscription];
        });

        $this->billing->recordTimeline($tenant->id, 'provisioned', 'Tenant provisionado automaticamente após pagamento.');

        // NFS-e da primeira cobrança (se habilitada).
        if ($signup->first_payment_id && $payment = BillingPayment::where('asaas_payment_id', $signup->first_payment_id)->first()) {
            $this->billing->scheduleNfse($payment);
        }

        $this->sendWelcome($tenant, $user, $tempPassword);

        return $tenant;
    }

    private function sendWelcome(Tenant $tenant, User $user, string $tempPassword): void
    {
        $domain = $tenant->domains()->where('active', true)->value('domain')
            ?? $tenant->domains()->value('domain');

        // Link mágico: signed URL relativo (validado ignorando domínio) prefixado pelo domínio do tenant.
        $signedPath = URL::temporarySignedRoute('first-access.login', now()->addDays(7), ['user' => $user->id], absolute: false);
        $magicLink = $domain ? "https://{$domain}{$signedPath}" : url($signedPath);
        $loginUrl = $domain ? "https://{$domain}/login" : url('/login');

        $this->billing->recordTimeline($tenant->id, 'email', 'E-mail de boas-vindas / primeiro acesso enviado.');

        Mail::to($user->email)->queue(new TenantWelcomeMail(
            tenantName: $tenant->name,
            magicLink: $magicLink,
            loginUrl: $loginUrl,
            email: $user->email,
            tempPassword: $tempPassword,
        ));
    }
}
