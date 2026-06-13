<?php

namespace App\Services\Billing;

use App\Models\BillingPayment;
use App\Models\BillingSetting;
use App\Models\BillingSubscription;
use Illuminate\Support\Carbon;

/**
 * Régua de cobrança e bloqueio automático (job diário). Avalia a fatura aberta de cada assinatura,
 * dispara os e-mails nos prazos configurados (D-3 → D+12), suspende em D+15 e aplica o
 * desbloqueio por confiança quando elegível. Também expira carências vencidas.
 */
class DunningService
{
    public function __construct(private readonly BillingService $billing) {}

    /** @return array{processed:int,suspended:int,trust:int,emails:int} */
    public function run(?Carbon $today = null): array
    {
        $today = $today ?: Carbon::today();
        $settings = BillingSetting::current();
        $stats = ['processed' => 0, 'suspended' => 0, 'trust' => 0, 'emails' => 0];

        $subscriptions = BillingSubscription::query()
            ->whereIn('status', [
                BillingSubscription::STATUS_ACTIVE,
                BillingSubscription::STATUS_OVERDUE,
                BillingSubscription::STATUS_GRACE_MANUAL,
                BillingSubscription::STATUS_GRACE_TRUST,
            ])
            ->with('tenant')
            ->get();

        foreach ($subscriptions as $subscription) {
            $stats['processed']++;
            $this->processSubscription($subscription, $settings, $today, $stats);
        }

        return $stats;
    }

    private function processSubscription(BillingSubscription $subscription, BillingSetting $settings, Carbon $today, array &$stats): void
    {
        // Carências (manual/confiança) vencidas e ainda sem pagamento → suspende.
        if ($subscription->inGrace()) {
            if ($subscription->grace_until && $today->gt($subscription->grace_until)) {
                $this->billing->suspend($subscription, 'Carência expirada sem pagamento.');
                $stats['suspended']++;
            }

            return;
        }

        $openPayment = $this->openPayment($subscription);
        if (! $openPayment || ! $openPayment->due_date) {
            return;
        }

        $daysFromDue = $openPayment->due_date->copy()->startOfDay()->diffInDays($today->copy()->startOfDay(), false);
        $state = $subscription->dunning_state ?? [];
        $key = $openPayment->asaas_payment_id;
        $sent = $state[$key] ?? [];

        $stage = $this->stageFor($daysFromDue, $settings);

        if ($stage === 'suspend') {
            if ($this->isTrustEligible($subscription, $settings, $today)) {
                $until = $today->copy()->addDays($settings->trust_grace_days);
                $this->billing->grantTrustGrace($subscription, $until);
                $stats['trust']++;
            } else {
                $this->billing->suspend($subscription, "Bloqueio automático por inadimplência (D+{$settings->suspend_day}).");
                $stats['suspended']++;
            }

            return;
        }

        if ($stage && ! in_array($stage, $sent, true)) {
            $this->billing->sendDunningEmail($subscription, $stage);
            $sent[] = $stage;
            $state[$key] = $sent;
            $subscription->update(['dunning_state' => $state]);
            $stats['emails']++;
        }
    }

    /** Mapeia o número de dias relativo ao vencimento para um estágio da régua. */
    private function stageFor(int $daysFromDue, BillingSetting $settings): ?string
    {
        if ($daysFromDue >= $settings->suspend_day) {
            return 'suspend';
        }

        return match (true) {
            $daysFromDue === -$settings->reminder_days_before => 'reminder',
            $daysFromDue === $settings->overdue_day_1 => 'overdue_1',
            $daysFromDue === $settings->overdue_day_2 => 'overdue_2',
            $daysFromDue === $settings->overdue_day_3 => 'overdue_3',
            default => null,
        };
    }

    /**
     * Elegibilidade para desbloqueio por confiança: cliente antigo, histórico em dia e sem
     * cortesia recente. Todos os critérios são configuráveis.
     */
    public function isTrustEligible(BillingSubscription $subscription, BillingSetting $settings, Carbon $today): bool
    {
        if (! $settings->trust_unlock_enabled) {
            return false;
        }

        // N meses como cliente
        $started = $subscription->started_at ?? $subscription->created_at;
        if (! $started || $started->copy()->addMonths($settings->trust_min_months)->gt($today)) {
            return false;
        }

        // Sem outra carência por confiança nos últimos Z meses
        if ($subscription->last_trust_grace_at
            && $subscription->last_trust_grace_at->copy()->addMonths($settings->trust_cooldown_months)->gt($today)) {
            return false;
        }

        // 100% das faturas anteriores pagas dentro da tolerância
        $paid = $subscription->payments()
            ->whereIn('status', BillingPayment::PAID_STATUSES)
            ->whereNotNull('payment_date')
            ->whereNotNull('due_date')
            ->get();

        if ($paid->isEmpty()) {
            return false; // sem histórico, não é "bom pagador comprovado"
        }

        foreach ($paid as $payment) {
            $lateDays = $payment->due_date->copy()->startOfDay()->diffInDays($payment->payment_date->copy()->startOfDay(), false);
            if ($lateDays > $settings->trust_tolerance_days) {
                return false;
            }
        }

        return true;
    }

    /** A fatura aberta (não paga) mais recente da assinatura. */
    private function openPayment(BillingSubscription $subscription): ?BillingPayment
    {
        return $subscription->payments()
            ->whereNotIn('status', array_merge(BillingPayment::PAID_STATUSES, ['REFUNDED', 'DELETED']))
            ->orderByDesc('due_date')
            ->first();
    }
}
