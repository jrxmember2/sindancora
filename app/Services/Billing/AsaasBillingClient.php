<?php

namespace App\Services\Billing;

use App\Services\Payments\AsaasException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP fino para a API do Asaas usando a conta ÚNICA da plataforma (billing SaaS),
 * configurada por ambiente (config/services.php → asaas_billing). Sem regra de negócio.
 *
 * @see https://docs.asaas.com/
 */
class AsaasBillingClient
{
    /** @param array<string,mixed>|null $config Override (testes); senão usa config('services.asaas_billing'). */
    public function __construct(private readonly ?array $config = null) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    // --- Customers ---

    public function createCustomer(array $payload): array
    {
        return $this->post('/customers', $payload);
    }

    // --- Subscriptions (assinatura recorrente) ---

    public function createSubscription(array $payload): array
    {
        return $this->post('/subscriptions', $payload);
    }

    public function getSubscription(string $id): array
    {
        return $this->get("/subscriptions/{$id}");
    }

    public function cancelSubscription(string $id): array
    {
        return $this->handle($this->request()->delete("/subscriptions/{$id}"));
    }

    /** Lista os pagamentos de uma assinatura. */
    public function listSubscriptionPayments(string $subscriptionId): array
    {
        return $this->get("/subscriptions/{$subscriptionId}/payments");
    }

    // --- Payments ---

    public function getPayment(string $id): array
    {
        return $this->get("/payments/{$id}");
    }

    public function getPixQrCode(string $id): array
    {
        return $this->get("/payments/{$id}/pixQrCode");
    }

    // --- Invoices (NFS-e) ---

    /** Agenda a NFS-e (emissão automática quando o pagamento for confirmado). */
    public function scheduleInvoice(array $payload): array
    {
        return $this->post('/invoices', $payload);
    }

    public function getInvoice(string $id): array
    {
        return $this->get("/invoices/{$id}");
    }

    /** Verifica a credencial / dados da empresa (usado pelo "Testar conexão"). */
    public function myAccount(): array
    {
        return $this->get('/myAccount');
    }

    // --- HTTP ---

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
        return Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'access_token' => $this->apiKey(),
                'User-Agent' => 'SindAncora-Billing',
            ])
            ->acceptJson()
            ->timeout(20);
    }

    private function handle(Response $response): array
    {
        if ($response->failed()) {
            Log::warning('Asaas billing: chamada falhou', [
                'status' => $response->status(),
                // Nunca logamos payload de cartão; o Asaas tokeniza no lado dele.
                'body' => $response->json('errors') ?? null,
            ]);

            throw AsaasException::fromResponse($response->json() ?? [], $response->status());
        }

        return $response->json() ?? [];
    }

    private function config(string $key, mixed $default = null): mixed
    {
        if ($this->config !== null) {
            return $this->config[$key] ?? $default;
        }

        return config("services.asaas_billing.{$key}", $default);
    }

    private function apiKey(): ?string
    {
        return $this->config('api_key');
    }

    private function baseUrl(): string
    {
        if ($explicit = $this->config('base_url')) {
            return $explicit;
        }

        $env = $this->config('environment', 'sandbox') === 'production' ? 'production' : 'sandbox';

        return $this->config($env, 'https://sandbox.asaas.com/api/v3');
    }
}
