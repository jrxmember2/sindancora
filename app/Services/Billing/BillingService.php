<?php

namespace App\Services\Billing;

use App\Jobs\ProvisionTenantFromSignup;
use App\Mail\Billing\BillingDunningMail;
use App\Models\BillingPayment;
use App\Models\BillingSetting;
use App\Models\BillingSubscription;
use App\Models\BillingTimelineEntry;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\AsaasException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Camada de domínio do billing SaaS (plataforma → tenant): checkout, conciliação de webhook,
 * gatilho de provisionamento, transições de estado da assinatura e agendamento da NFS-e.
 * O HTTP fica no AsaasBillingClient; o Asaas é a fonte da verdade dos pagamentos.
 */
class BillingService
{
    public function __construct(private readonly AsaasBillingClient $client) {}

    public function client(): AsaasBillingClient
    {
        return $this->client;
    }

    // ----------------------------------------------------------------------------------
    // Checkout público
    // ----------------------------------------------------------------------------------

    /**
     * Cria o cliente + assinatura no Asaas a partir de um pré-cadastro e retorna os dados de
     * pagamento da primeira cobrança (PIX/QR, link da fatura, boleto).
     *
     * @return array{signup: PendingSignup, payment: array}
     */
    public function startCheckout(array $data): array
    {
        $plan = Plan::active()->public()->findOrFail($data['plan_id']);
        $cycle = ($data['billing_cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $billingType = in_array($data['billing_type'] ?? null, ['PIX', 'CREDIT_CARD', 'BOLETO'], true)
            ? $data['billing_type'] : 'PIX';
        $value = (float) ($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

        if ($value <= 0) {
            throw new AsaasException('Este plano não tem preço configurado para o ciclo escolhido.');
        }

        $signup = PendingSignup::create([
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'billing_type' => $billingType,
            'value' => $value,
            'company_name' => $data['company_name'],
            'document' => preg_replace('/\D/', '', (string) ($data['document'] ?? '')) ?: null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'admin_name' => $data['admin_name'],
            'status' => 'pending',
        ]);

        try {
            $customer = $this->client->createCustomer([
                'name' => $signup->company_name,
                'cpfCnpj' => $signup->document,
                'email' => $signup->email,
                'mobilePhone' => $signup->phone ? preg_replace('/\D/', '', $signup->phone) : null,
                'externalReference' => $signup->id,
            ]);

            $subscription = $this->client->createSubscription([
                'customer' => $customer['id'],
                'billingType' => $billingType,
                'value' => $value,
                'nextDueDate' => Carbon::today()->toDateString(),
                'cycle' => $cycle === 'yearly' ? 'YEARLY' : 'MONTHLY',
                'description' => "Assinatura Sindâncora — {$plan->display_name}",
                'externalReference' => $signup->id,
            ]);

            $signup->update([
                'asaas_customer_id' => $customer['id'],
                'asaas_subscription_id' => $subscription['id'],
            ]);

            $payment = $this->resolveFirstPayment($signup);

            return ['signup' => $signup, 'payment' => $payment];
        } catch (AsaasException $e) {
            $signup->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** Busca a 1ª cobrança da assinatura e monta os dados de pagamento (PIX/QR ou link). */
    public function resolveFirstPayment(PendingSignup $signup): array
    {
        if (! $signup->asaas_subscription_id) {
            return [];
        }

        $payments = $this->client->listSubscriptionPayments($signup->asaas_subscription_id);
        $first = $payments['data'][0] ?? null;

        if (! $first) {
            return [];
        }

        $signup->update(['first_payment_id' => $first['id']]);
        $this->upsertPayment($first, isFirst: true);

        $out = [
            'id' => $first['id'],
            'status' => $first['status'] ?? null,
            'value' => $first['value'] ?? $signup->value,
            'due_date' => $first['dueDate'] ?? null,
            'invoice_url' => $first['invoiceUrl'] ?? null,
            'bank_slip_url' => $first['bankSlipUrl'] ?? null,
            'billing_type' => $signup->billing_type,
        ];

        if ($signup->billing_type === 'PIX') {
            try {
                $pix = $this->client->getPixQrCode($first['id']);
                $out['pix_payload'] = $pix['payload'] ?? null;
                $out['pix_qrcode'] = $pix['encodedImage'] ?? null;
            } catch (\Throwable $e) {
                Log::info('Asaas billing: PIX QR indisponível', ['error' => $e->getMessage()]);
            }
        }

        return $out;
    }

    // ----------------------------------------------------------------------------------
    // Conciliação de webhook
    // ----------------------------------------------------------------------------------

    /**
     * Concilia um evento de pagamento do webhook. Idempotente: pode ser reprocessado.
     * Espera o payload completo (`{event, payment:{...}}`).
     */
    public function handlePaymentEvent(string $event, array $payment): void
    {
        if (empty($payment['id'])) {
            return;
        }

        $local = $this->upsertPayment($payment);
        $isPaidEvent = in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_RECEIVED_IN_CASH'], true);

        // Contexto: assinatura já provisionada?
        $subscription = $this->resolveSubscription($payment);

        if ($subscription) {
            $local->forceFill([
                'tenant_id' => $subscription->tenant_id,
                'billing_subscription_id' => $subscription->id,
            ])->save();

            match (true) {
                $isPaidEvent => $this->onSubscriptionPaid($subscription, $local),
                $event === 'PAYMENT_OVERDUE' => $this->onSubscriptionOverdue($subscription, $local),
                in_array($event, ['PAYMENT_REFUNDED', 'PAYMENT_DELETED', 'PAYMENT_CHARGEBACK_REQUESTED'], true) => $this->recordTimeline($subscription->tenant_id, 'payment', "Pagamento {$event} ({$local->asaas_payment_id})."),
                default => null,
            };

            return;
        }

        // Fase de signup: provisiona no 1º pagamento compensado.
        $signup = $this->resolveSignup($payment);
        if ($signup && $isPaidEvent && $signup->status !== 'provisioned') {
            $signup->update(['status' => 'paid', 'paid_at' => now()]);
            ProvisionTenantFromSignup::dispatch($signup->id);
        }
    }

    /** Upsert do espelho local de um payment do Asaas. */
    public function upsertPayment(array $payment, bool $isFirst = false): BillingPayment
    {
        return BillingPayment::updateOrCreate(
            ['asaas_payment_id' => $payment['id']],
            array_filter([
                'asaas_subscription_id' => $payment['subscription'] ?? null,
                'asaas_customer_id' => $payment['customer'] ?? null,
                'status' => $payment['status'] ?? 'PENDING',
                'billing_type' => $payment['billingType'] ?? null,
                'value' => $payment['value'] ?? 0,
                'net_value' => $payment['netValue'] ?? null,
                'due_date' => $payment['dueDate'] ?? null,
                'payment_date' => $payment['paymentDate'] ?? $payment['confirmedDate'] ?? null,
                'invoice_url' => $payment['invoiceUrl'] ?? null,
                'bank_slip_url' => $payment['bankSlipUrl'] ?? null,
                'is_first_payment' => $isFirst ?: null,
                'synced_at' => now(),
            ], fn ($v) => $v !== null),
        );
    }

    // ----------------------------------------------------------------------------------
    // Transições de estado
    // ----------------------------------------------------------------------------------

    /** Pagamento compensado de uma assinatura existente → reativa/normaliza e agenda NFS-e. */
    public function onSubscriptionPaid(BillingSubscription $subscription, BillingPayment $payment): void
    {
        $wasBlocked = ! $subscription->grantsAccess() || $subscription->inGrace()
            || $subscription->status === BillingSubscription::STATUS_OVERDUE;

        $subscription->update([
            'status' => BillingSubscription::STATUS_ACTIVE,
            'next_due_date' => $this->nextDueAfter($subscription),
            'grace_until' => null,
            'grace_reason' => null,
            'grace_granted_by' => null,
            'grace_granted_at' => null,
            'dunning_state' => null,
        ]);

        $tenant = $subscription->tenant;
        if ($tenant && $tenant->status === 'suspended') {
            $tenant->update(['status' => 'active']);
            $this->forgetTenantCache($tenant);
            $this->recordTimeline($tenant->id, 'unblocked', 'Reativado automaticamente após pagamento.');
        }

        $this->recordTimeline($subscription->tenant_id, 'payment', 'Pagamento confirmado: R$ '.number_format((float) $payment->value, 2, ',', '.').'.', [
            'payment_id' => $payment->asaas_payment_id,
        ]);

        if ($wasBlocked) {
            Log::info('Billing: assinatura normalizada após pagamento', ['tenant' => $subscription->tenant_id]);
        }

        $this->scheduleNfse($payment);
    }

    public function onSubscriptionOverdue(BillingSubscription $subscription, BillingPayment $payment): void
    {
        if ($subscription->status === BillingSubscription::STATUS_ACTIVE) {
            $subscription->update(['status' => BillingSubscription::STATUS_OVERDUE]);
        }
        $this->recordTimeline($subscription->tenant_id, 'payment', 'Fatura vencida (overdue) no gateway.', [
            'payment_id' => $payment->asaas_payment_id,
        ]);
    }

    /** Bloqueia o tenant (D+15 sem pagamento, ou expiração de carência). */
    public function suspend(BillingSubscription $subscription, string $reason): void
    {
        $subscription->update([
            'status' => BillingSubscription::STATUS_SUSPENDED,
            'grace_until' => null,
        ]);

        $tenant = $subscription->tenant;
        if ($tenant && $tenant->status !== 'suspended') {
            $tenant->update(['status' => 'suspended']);
            $this->forgetTenantCache($tenant);
        }

        $this->recordTimeline($subscription->tenant_id, 'blocked', $reason);
        $this->sendDunningEmail($subscription, 'suspended');
    }

    /** Desbloqueio manual no super admin → grace_manual com prazo. */
    public function grantManualGrace(BillingSubscription $subscription, string $reason, Carbon $until, User $actor): void
    {
        $subscription->update([
            'status' => BillingSubscription::STATUS_GRACE_MANUAL,
            'grace_until' => $until->toDateString(),
            'grace_reason' => $reason,
            'grace_granted_by' => $actor->id,
            'grace_granted_at' => now(),
        ]);

        $tenant = $subscription->tenant;
        if ($tenant && $tenant->status !== 'active') {
            $tenant->update(['status' => 'active']);
            $this->forgetTenantCache($tenant);
        }

        $this->recordTimeline($subscription->tenant_id, 'unblocked',
            "Desbloqueio manual até {$until->format('d/m/Y')}. Motivo: {$reason}",
            ['until' => $until->toDateString()], $actor);
    }

    /** Revoga uma carência ativa (manual ou por confiança) → volta a suspended. */
    public function revokeGrace(BillingSubscription $subscription, User $actor): void
    {
        $this->suspend($subscription, 'Carência revogada manualmente por '.$actor->name.'.');
    }

    /** Carência por confiança (automática, na régua) → grace_trust por N dias. */
    public function grantTrustGrace(BillingSubscription $subscription, Carbon $until): void
    {
        $subscription->update([
            'status' => BillingSubscription::STATUS_GRACE_TRUST,
            'grace_until' => $until->toDateString(),
            'grace_reason' => 'Cortesia automática (bom pagador).',
            'trust_grace_count' => $subscription->trust_grace_count + 1,
            'last_trust_grace_at' => now(),
        ]);

        $this->recordTimeline($subscription->tenant_id, 'grace',
            "Desbloqueio por confiança até {$until->format('d/m/Y')} (cortesia automática).",
            ['until' => $until->toDateString()]);
        $this->sendDunningEmail($subscription, 'trust');
    }

    public function cancel(BillingSubscription $subscription): void
    {
        if ($subscription->asaas_subscription_id) {
            try {
                $this->client->cancelSubscription($subscription->asaas_subscription_id);
            } catch (\Throwable $e) {
                Log::warning('Billing: falha ao cancelar assinatura no Asaas', ['error' => $e->getMessage()]);
            }
        }

        $subscription->update([
            'status' => BillingSubscription::STATUS_CANCELED,
            'canceled_at' => now(),
        ]);
        $this->recordTimeline($subscription->tenant_id, 'canceled', 'Assinatura cancelada.');
    }

    // ----------------------------------------------------------------------------------
    // NFS-e
    // ----------------------------------------------------------------------------------

    /** Agenda a NFS-e da cobrança (emissão automática na confirmação do pagamento). */
    public function scheduleNfse(BillingPayment $payment): void
    {
        $settings = BillingSetting::current();

        if (! $settings->nfse_enabled || $payment->invoice_id) {
            return; // desligado ou já agendada
        }

        if (blank($settings->nfse_service_description) || blank($settings->nfse_municipal_service_code)) {
            $payment->update(['nfse_status' => 'error', 'nfse_error' => 'Configuração fiscal municipal pendente.']);

            return;
        }

        try {
            $invoice = $this->client->scheduleInvoice([
                'payment' => $payment->asaas_payment_id,
                'serviceDescription' => $settings->nfse_service_description,
                'observations' => $settings->nfse_observations,
                'value' => (float) $payment->value,
                'deductions' => $settings->nfse_deductions ? (float) $settings->nfse_deductions : 0,
                'effectiveDate' => Carbon::today()->toDateString(),
                'municipalServiceCode' => $settings->nfse_municipal_service_code,
                'taxes' => [
                    'iss' => $settings->nfse_iss_tax ? (float) $settings->nfse_iss_tax : 0,
                ],
            ]);

            $payment->update([
                'invoice_id' => $invoice['id'] ?? null,
                'nfse_status' => 'scheduled',
                'nfse_pdf_url' => $invoice['pdfUrl'] ?? null,
                'nfse_xml_url' => $invoice['xmlUrl'] ?? null,
                'nfse_error' => null,
            ]);
        } catch (\Throwable $e) {
            $payment->update(['nfse_status' => 'error', 'nfse_error' => $e->getMessage()]);
            Log::warning('Billing: falha ao agendar NFS-e', ['payment' => $payment->id, 'error' => $e->getMessage()]);
        }
    }

    // ----------------------------------------------------------------------------------
    // Auxiliares
    // ----------------------------------------------------------------------------------

    public function recordTimeline(string $tenantId, string $type, string $description, array $meta = [], ?User $actor = null): void
    {
        BillingTimelineEntry::create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'description' => $description,
            'meta' => $meta ?: null,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
        ]);
    }

    public function sendDunningEmail(BillingSubscription $subscription, string $stage): void
    {
        $tenant = $subscription->tenant;
        $email = $tenant?->email ?: $tenant?->users()->whereNotNull('email')->value('email');

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->queue(new BillingDunningMail($subscription->id, $stage));
            $this->recordTimeline($subscription->tenant_id, 'email', "E-mail de cobrança enviado (estágio: {$stage}).");
        } catch (\Throwable $e) {
            Log::warning('Billing: falha ao enfileirar e-mail de cobrança', ['error' => $e->getMessage()]);
        }
    }

    public function nextDueAfter(BillingSubscription $subscription): string
    {
        $base = $subscription->next_due_date ? $subscription->next_due_date->copy() : Carbon::today();
        if ($base->isPast()) {
            $base = Carbon::today();
        }

        return $subscription->billing_cycle === 'yearly'
            ? $base->addYear()->toDateString()
            : $base->addMonth()->toDateString();
    }

    private function resolveSubscription(array $payment): ?BillingSubscription
    {
        if (! empty($payment['subscription'])) {
            return BillingSubscription::where('asaas_subscription_id', $payment['subscription'])->first();
        }

        return null;
    }

    private function resolveSignup(array $payment): ?PendingSignup
    {
        if (! empty($payment['subscription'])) {
            $signup = PendingSignup::where('asaas_subscription_id', $payment['subscription'])->first();
            if ($signup) {
                return $signup;
            }
        }

        if (! empty($payment['externalReference'])) {
            return PendingSignup::find($payment['externalReference']);
        }

        return null;
    }

    private function forgetTenantCache(Tenant $tenant): void
    {
        foreach ($tenant->domains as $domain) {
            Cache::forget("tenant:domain:{$domain->domain}");
        }
    }
}
