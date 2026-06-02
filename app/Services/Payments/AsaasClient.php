<?php

namespace App\Services\Payments;

use App\Models\TenantPaymentSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP fino para a API do Asaas. Uma instância é amarrada a um TenantPaymentSetting
 * (ambiente + chave do tenant). Não contém regra de negócio — só fala com o gateway.
 *
 * @see https://docs.asaas.com/
 */
class AsaasClient
{
    public function __construct(private readonly TenantPaymentSetting $setting) {}

    /** Cria (ou retorna) um cliente no Asaas. @return array dados do cliente (inclui `id`). */
    public function createCustomer(array $payload): array
    {
        return $this->post('/customers', $payload);
    }

    /** Cria uma cobrança (pagamento) no Asaas. @return array dados da cobrança (inclui `id`, `invoiceUrl`, `bankSlipUrl`). */
    public function createPayment(array $payload): array
    {
        return $this->post('/payments', $payload);
    }

    /** Busca uma cobrança pelo id. */
    public function getPayment(string $id): array
    {
        return $this->get("/payments/{$id}");
    }

    /** QR Code / copia-e-cola do PIX de uma cobrança. @return array{encodedImage?:string,payload?:string} */
    public function getPixQrCode(string $id): array
    {
        return $this->get("/payments/{$id}/pixQrCode");
    }

    /** Linha digitável do boleto de uma cobrança. @return array{identificationField?:string,barCode?:string} */
    public function getIdentificationField(string $id): array
    {
        return $this->get("/payments/{$id}/identificationField");
    }

    /** Remove (cancela) uma cobrança no Asaas. */
    public function deletePayment(string $id): array
    {
        return $this->handle($this->request()->delete("/payments/{$id}"));
    }

    /** Verifica a credencial (usado pelo "Testar conexão"). */
    public function myAccount(): array
    {
        return $this->get('/myAccount');
    }

    private function get(string $path): array
    {
        return $this->handle($this->request()->get($path));
    }

    private function post(string $path, array $payload): array
    {
        return $this->handle($this->request()->post($path, $payload));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->setting->baseUrl())
            ->withHeaders([
                'access_token' => $this->setting->api_key,
                'User-Agent' => 'SindAncora',
            ])
            ->acceptJson()
            ->timeout(20);
    }

    private function handle(Response $response): array
    {
        if ($response->failed()) {
            throw AsaasException::fromResponse($response->json() ?? [], $response->status());
        }

        return $response->json() ?? [];
    }
}
