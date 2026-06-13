<?php

namespace Tests\Feature\Billing;

use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Services\Billing\DunningService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DunningTest extends BillingTestCase
{
    private function openPayment(BillingSubscription $sub, int $daysOverdue): BillingPayment
    {
        return BillingPayment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $sub->tenant_id,
            'billing_subscription_id' => $sub->id,
            'asaas_payment_id' => 'pay_'.Str::random(8),
            'asaas_subscription_id' => $sub->asaas_subscription_id,
            'status' => 'OVERDUE',
            'value' => 199.90,
            'due_date' => Carbon::today()->subDays($daysOverdue)->toDateString(),
        ]);
    }

    public function test_suspends_tenant_at_d_plus_15(): void
    {
        Mail::fake();
        $plan = $this->makePlan();
        $tenant = $this->makeTenant();
        // started_at recente → não elegível ao desbloqueio por confiança
        $sub = $this->makeSubscription($tenant, $plan, ['started_at' => now()->subMonth()]);
        $this->openPayment($sub, 15);

        app(DunningService::class)->run(Carbon::today());

        $this->assertEquals(BillingSubscription::STATUS_SUSPENDED, $sub->fresh()->status);
        $this->assertEquals('suspended', $tenant->fresh()->status);
    }

    public function test_sends_reminder_email_three_days_before_due(): void
    {
        Mail::fake();
        $plan = $this->makePlan();
        $tenant = $this->makeTenant();
        $sub = $this->makeSubscription($tenant, $plan);
        // vence em 3 dias (D-3)
        BillingPayment::create([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'billing_subscription_id' => $sub->id,
            'asaas_payment_id' => 'pay_'.Str::random(8), 'status' => 'PENDING', 'value' => 199.90,
            'due_date' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $stats = app(DunningService::class)->run(Carbon::today());

        $this->assertEquals(1, $stats['emails']);
        $this->assertEquals(BillingSubscription::STATUS_ACTIVE, $sub->fresh()->status);
        $this->assertContains('reminder', $sub->fresh()->dunning_state[array_key_first($sub->fresh()->dunning_state)]);
    }

    public function test_trust_grace_when_eligible_instead_of_suspend(): void
    {
        Mail::fake();
        $plan = $this->makePlan();
        $tenant = $this->makeTenant();
        // Cliente antigo (7 meses), sem cortesia prévia.
        $sub = $this->makeSubscription($tenant, $plan, ['started_at' => now()->subMonths(7)]);

        // Histórico 100% em dia.
        BillingPayment::create([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'billing_subscription_id' => $sub->id,
            'asaas_payment_id' => 'pay_paid', 'status' => 'RECEIVED', 'value' => 199.90,
            'due_date' => Carbon::today()->subMonths(2)->toDateString(),
            'payment_date' => Carbon::today()->subMonths(2)->toDateString(),
        ]);
        $this->openPayment($sub, 15);

        app(DunningService::class)->run(Carbon::today());

        $this->assertEquals(BillingSubscription::STATUS_GRACE_TRUST, $sub->fresh()->status);
        $this->assertEquals('active', $tenant->fresh()->status, 'Tenant elegível não deve ser bloqueado.');
        $this->assertEquals(1, $sub->fresh()->trust_grace_count);
    }

    public function test_expired_manual_grace_suspends(): void
    {
        Mail::fake();
        $plan = $this->makePlan();
        $tenant = $this->makeTenant();
        $sub = $this->makeSubscription($tenant, $plan, [
            'status' => BillingSubscription::STATUS_GRACE_MANUAL,
            'grace_until' => Carbon::yesterday()->toDateString(),
        ]);

        app(DunningService::class)->run(Carbon::today());

        $this->assertEquals(BillingSubscription::STATUS_SUSPENDED, $sub->fresh()->status);
        $this->assertEquals('suspended', $tenant->fresh()->status);
    }

    public function test_active_manual_grace_is_not_suspended(): void
    {
        Mail::fake();
        $plan = $this->makePlan();
        $tenant = $this->makeTenant();
        $sub = $this->makeSubscription($tenant, $plan, [
            'status' => BillingSubscription::STATUS_GRACE_MANUAL,
            'grace_until' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        app(DunningService::class)->run(Carbon::today());

        $this->assertEquals(BillingSubscription::STATUS_GRACE_MANUAL, $sub->fresh()->status);
    }
}
