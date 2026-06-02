<?php

namespace App\Services\Payments;

use App\Mail\ChargeIssuedMail;
use App\Models\Charge;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\TenantPaymentSetting;
use App\Services\ChargeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Camada de domínio da integração Asaas: garante o cliente do morador, emite boleto/PIX a partir
 * de uma Charge, concilia o pagamento pelo webhook e dispara a 2ª via. O HTTP fica no AsaasClient.
 */
class AsaasService
{
    public function __construct(private readonly ChargeService $charges) {}

    /** Configuração Asaas utilizável (ligada + com chave) de um tenant, ou null. */
    public function settingFor(Tenant $tenant): ?TenantPaymentSetting
    {
        $setting = $tenant->paymentSetting()->first();

        return $setting && $setting->isUsable() ? $setting : null;
    }

    /** Garante o cliente do morador no Asaas, persistindo o id na Pessoa. */
    public function ensureCustomer(Person $person, TenantPaymentSetting $setting): string
    {
        if (filled($person->gateway_customer_id)) {
            return $person->gateway_customer_id;
        }

        $data = $this->client($setting)->createCustomer([
            'name' => $person->name,
            'cpfCnpj' => preg_replace('/\D/', '', (string) $person->cpf),
            'email' => $person->email ?: null,
            'mobilePhone' => $person->phone ? preg_replace('/\D/', '', $person->phone) : null,
            'externalReference' => $person->id,
        ]);

        $person->forceFill(['gateway_customer_id' => $data['id']])->save();

        return $data['id'];
    }

    /**
     * Emite (ou ressincroniza) boleto + PIX de uma cobrança no Asaas e grava os dados no registro.
     * Idempotente: se já houver gateway_payment_id, apenas atualiza os dados.
     */
    public function issueCharge(Charge $charge): Charge
    {
        $setting = $this->settingFor($charge->tenant);
        if (! $setting) {
            throw new AsaasException('Integração de pagamento não está habilitada para este condomínio.');
        }

        $person = $charge->person;
        if (! $person || blank($person->cpf)) {
            throw new AsaasException('A cobrança precisa de um responsável com CPF/CNPJ para emitir boleto/PIX.');
        }

        $client = $this->client($setting);

        if ($charge->hasGatewayCharge()) {
            $payment = $client->getPayment($charge->gateway_payment_id);
        } else {
            $customerId = $this->ensureCustomer($person, $setting);
            $payment = $client->createPayment($this->paymentPayload($charge, $setting, $customerId));
        }

        return $this->syncFromPayment($charge, $client, $payment);
    }

    /**
     * Concilia um evento de webhook do Asaas com a cobrança correspondente.
     * Espera o payload completo do webhook (`{event, payment:{...}}`).
     */
    public function reconcile(array $payload): void
    {
        $event = $payload['event'] ?? null;
        $payment = $payload['payment'] ?? [];
        $charge = $this->resolveCharge($payment);

        if (! $charge) {
            Log::warning('Asaas webhook: cobrança não encontrada', ['event' => $event, 'payment' => $payment['id'] ?? null]);

            return;
        }

        $charge->forceFill(['gateway_status' => $payment['status'] ?? $charge->gateway_status]);

        match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_RECEIVED_IN_CASH' => $this->markPaid($charge, $payment),
            'PAYMENT_OVERDUE' => $this->markStatus($charge, 'overdue'),
            'PAYMENT_DELETED', 'PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED' => $this->markStatus($charge, 'cancelled'),
            'PAYMENT_RESTORED', 'PAYMENT_UPDATED' => $charge->status === 'paid' ? null : $this->markStatus($charge, 'pending'),
            default => $charge->save(),
        };
    }

    /** Garante a emissão e dispara a 2ª via por e-mail ao morador responsável. */
    public function sendSecondCopy(Charge $charge): void
    {
        if (! $charge->hasGatewayCharge()) {
            $this->issueCharge($charge);
        }

        $email = $charge->person?->email;
        if ($email) {
            Mail::to($email)->queue(new ChargeIssuedMail($charge->fresh()));
        }
    }

    // --- internos ---

    private function markPaid(Charge $charge, array $payment): void
    {
        if ($charge->status === 'paid') {
            return; // idempotência: evento repetido não re-registra pagamento
        }

        $this->charges->registerPayment($charge, [
            'paid_at' => $payment['paymentDate'] ?? $payment['confirmedDate'] ?? Carbon::now()->toDateString(),
            'paid_amount' => $payment['value'] ?? $charge->amount,
            'payment_method' => $payment['billingType'] ?? 'gateway',
            'notes' => 'Conciliado automaticamente via Asaas.',
        ]);
    }

    private function markStatus(Charge $charge, string $status): void
    {
        $charge->forceFill(['status' => $status])->save();
    }

    private function syncFromPayment(Charge $charge, AsaasClient $client, array $payment): Charge
    {
        $pix = $this->safe(fn () => $client->getPixQrCode($payment['id']));
        $slip = $this->safe(fn () => $client->getIdentificationField($payment['id']));

        $charge->forceFill([
            'gateway' => 'asaas',
            'gateway_payment_id' => $payment['id'],
            'gateway_status' => $payment['status'] ?? null,
            'invoice_url' => $payment['invoiceUrl'] ?? null,
            'bank_slip_url' => $payment['bankSlipUrl'] ?? null,
            'bank_slip_line' => $slip['identificationField'] ?? null,
            'pix_payload' => $pix['payload'] ?? null,
            'pix_qrcode' => $pix['encodedImage'] ?? null,
            'gateway_synced_at' => Carbon::now(),
        ])->save();

        return $charge;
    }

    /** @return array{customer:string,billingType:string,value:float,dueDate:string,description:string,externalReference:string,fine?:array,interest?:array} */
    private function paymentPayload(Charge $charge, TenantPaymentSetting $setting, string $customerId): array
    {
        $payload = [
            'customer' => $customerId,
            'billingType' => $setting->billing_type ?: 'UNDEFINED',
            'value' => (float) $charge->amount,
            'dueDate' => $charge->due_date->toDateString(),
            'description' => $charge->description,
            'externalReference' => $charge->id,
        ];

        if ((float) $charge->fine_rate > 0) {
            $payload['fine'] = ['value' => (float) $charge->fine_rate, 'type' => 'PERCENTAGE'];
        }

        if ((float) $charge->interest_rate > 0) {
            $payload['interest'] = ['value' => (float) $charge->interest_rate];
        }

        return $payload;
    }

    /** Localiza a cobrança a partir do payload do webhook, ignorando o escopo de tenant. */
    private function resolveCharge(array $payment): ?Charge
    {
        $query = Charge::withoutGlobalScope('tenant');

        if (! empty($payment['externalReference'])) {
            $charge = (clone $query)->find($payment['externalReference']);
            if ($charge) {
                return $charge;
            }
        }

        if (! empty($payment['id'])) {
            return $query->where('gateway_payment_id', $payment['id'])->first();
        }

        return null;
    }

    private function client(TenantPaymentSetting $setting): AsaasClient
    {
        return new AsaasClient($setting);
    }

    /** Executa uma chamada opcional ao gateway sem deixar uma falha derrubar a emissão. */
    private function safe(callable $fn): array
    {
        try {
            return $fn() ?: [];
        } catch (\Throwable $e) {
            Log::info('Asaas: dado opcional indisponível', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
