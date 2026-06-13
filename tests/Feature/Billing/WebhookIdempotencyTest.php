<?php

namespace Tests\Feature\Billing;

use App\Jobs\ProcessAsaasBillingWebhook;
use App\Jobs\ProvisionTenantFromSignup;
use App\Models\PaymentEvent;
use App\Models\PendingSignup;
use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class WebhookIdempotencyTest extends BillingTestCase
{
    private array $payload = [
        'id' => 'evt_123',
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => ['id' => 'pay_123', 'subscription' => 'sub_xyz', 'status' => 'CONFIRMED', 'value' => 199.90],
    ];

    public function test_rejects_invalid_token(): void
    {
        $res = $this->postJson('/api/webhooks/asaas/saas', $this->payload, ['asaas-access-token' => 'wrong']);

        $res->assertStatus(401);
        $this->assertSame(0, PaymentEvent::count());
    }

    public function test_persists_event_once_for_duplicate_deliveries(): void
    {
        Bus::fake();
        $headers = ['asaas-access-token' => 'test-webhook-token'];

        $this->postJson('/api/webhooks/asaas/saas', $this->payload, $headers)->assertOk();
        $this->postJson('/api/webhooks/asaas/saas', $this->payload, $headers)->assertOk();

        // Idempotência do registro: o mesmo event id grava uma única linha.
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_already_processed_event_is_not_requeued(): void
    {
        Bus::fake();
        $headers = ['asaas-access-token' => 'test-webhook-token'];

        $this->postJson('/api/webhooks/asaas/saas', $this->payload, $headers)->assertOk();
        PaymentEvent::where('asaas_event_id', 'evt_123')->update(['processed' => true, 'processed_at' => now()]);

        $res = $this->postJson('/api/webhooks/asaas/saas', $this->payload, $headers);

        $res->assertJson(['status' => 'duplicate']);
        Bus::assertDispatchedTimes(ProcessAsaasBillingWebhook::class, 1);
    }

    public function test_paid_event_for_signup_triggers_provisioning(): void
    {
        Bus::fake();
        $plan = $this->makePlan();
        $signup = PendingSignup::create([
            'id' => (string) Str::uuid(), 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'billing_type' => 'PIX', 'value' => 199.90, 'company_name' => 'Novo Cond',
            'email' => 'novo@cond.com', 'admin_name' => 'Síndico', 'status' => 'pending',
            'asaas_subscription_id' => 'sub_signup',
        ]);

        app(BillingService::class)->handlePaymentEvent('PAYMENT_CONFIRMED', [
            'id' => 'pay_first', 'subscription' => 'sub_signup', 'status' => 'CONFIRMED', 'value' => 199.90,
        ]);

        Bus::assertDispatched(ProvisionTenantFromSignup::class);
        $this->assertSame('paid', $signup->fresh()->status);
    }
}
