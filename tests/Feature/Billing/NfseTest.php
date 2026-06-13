<?php

namespace Tests\Feature\Billing;

use App\Models\BillingPayment;
use App\Services\Billing\AsaasBillingClient;
use App\Services\Billing\BillingService;
use Illuminate\Support\Str;
use Mockery;

class NfseTest extends BillingTestCase
{
    private function payment(): BillingPayment
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant();
        $sub = $this->makeSubscription($tenant, $plan);

        return BillingPayment::create([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'billing_subscription_id' => $sub->id,
            'asaas_payment_id' => 'pay_'.Str::random(8), 'status' => 'CONFIRMED', 'value' => 199.90,
        ]);
    }

    public function test_schedules_invoice_when_enabled_and_configured(): void
    {
        $this->settings()->update([
            'nfse_enabled' => true,
            'nfse_service_description' => 'Assinatura de software de gestão condominial',
            'nfse_municipal_service_code' => '1.07',
            'nfse_iss_tax' => 2.0,
        ]);

        $client = Mockery::mock(AsaasBillingClient::class);
        $client->shouldReceive('scheduleInvoice')->once()->andReturn([
            'id' => 'inv_123', 'pdfUrl' => 'https://asaas/inv.pdf', 'xmlUrl' => 'https://asaas/inv.xml',
        ]);
        app()->instance(AsaasBillingClient::class, $client);

        $payment = $this->payment();
        app(BillingService::class)->scheduleNfse($payment);

        $payment->refresh();
        $this->assertSame('inv_123', $payment->invoice_id);
        $this->assertSame('scheduled', $payment->nfse_status);
        $this->assertSame('https://asaas/inv.pdf', $payment->nfse_pdf_url);
    }

    public function test_does_nothing_when_nfse_disabled(): void
    {
        $this->settings()->update(['nfse_enabled' => false]);

        $client = Mockery::mock(AsaasBillingClient::class);
        $client->shouldNotReceive('scheduleInvoice');
        app()->instance(AsaasBillingClient::class, $client);

        $payment = $this->payment();
        app(BillingService::class)->scheduleNfse($payment);

        $this->assertNull($payment->fresh()->invoice_id);
    }

    public function test_marks_error_when_municipal_config_missing(): void
    {
        $this->settings()->update([
            'nfse_enabled' => true,
            'nfse_service_description' => null,
            'nfse_municipal_service_code' => null,
        ]);

        $client = Mockery::mock(AsaasBillingClient::class);
        $client->shouldNotReceive('scheduleInvoice');
        app()->instance(AsaasBillingClient::class, $client);

        $payment = $this->payment();
        app(BillingService::class)->scheduleNfse($payment);

        $this->assertSame('error', $payment->fresh()->nfse_status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
