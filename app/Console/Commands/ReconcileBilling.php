<?php

namespace App\Console\Commands;

use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Services\Billing\BillingService;
use Illuminate\Console\Command;

/**
 * Reconciliação diária: o Asaas é a fonte da verdade. Consulta a API e corrige divergências do
 * espelho local (pagamentos que compensaram sem webhook, status defasado etc.).
 */
class ReconcileBilling extends Command
{
    protected $signature = 'billing:reconcile';

    protected $description = 'Reconcilia os pagamentos das assinaturas SaaS com o Asaas (corrige divergências).';

    public function handle(BillingService $billing): int
    {
        if (! $billing->client()->isConfigured()) {
            $this->warn('Asaas billing não configurado (ASAAS_API_KEY ausente). Reconciliação ignorada.');

            return self::SUCCESS;
        }

        $subscriptions = BillingSubscription::query()
            ->whereNotNull('asaas_subscription_id')
            ->where('status', '!=', BillingSubscription::STATUS_CANCELED)
            ->with('tenant')
            ->get();

        $fixed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $payments = $billing->client()->listSubscriptionPayments($subscription->asaas_subscription_id);
            } catch (\Throwable $e) {
                $this->warn("Falha ao consultar {$subscription->id}: {$e->getMessage()}");

                continue;
            }

            foreach ($payments['data'] ?? [] as $payment) {
                $local = BillingPayment::where('asaas_payment_id', $payment['id'])->first();
                $remoteStatus = $payment['status'] ?? null;

                $synced = $billing->upsertPayment($payment);
                $synced->forceFill([
                    'tenant_id' => $subscription->tenant_id,
                    'billing_subscription_id' => $subscription->id,
                ])->save();

                // Pagamento compensado no Asaas mas não refletido localmente → normaliza.
                if (in_array($remoteStatus, BillingPayment::PAID_STATUSES, true)
                    && (! $local || ! in_array($local->status, BillingPayment::PAID_STATUSES, true))) {
                    $billing->onSubscriptionPaid($subscription->fresh(), $synced);
                    $fixed++;
                }
            }
        }

        $this->info("Reconciliação concluída. {$subscriptions->count()} assinatura(s) verificada(s); {$fixed} divergência(s) corrigida(s).");

        return self::SUCCESS;
    }
}
