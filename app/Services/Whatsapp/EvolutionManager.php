<?php

namespace App\Services\Whatsapp;

use App\Models\EvolutionSetting;
use App\Models\WhatsappConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Gerencia instâncias no servidor Evolution API (auto-hospedado), usando a chave GLOBAL do
 * servidor (config services.evolution.key). Criar instância, parear (QR), estado, logout,
 * excluir e enviar mensagens. Uma instância da Evolution = uma "conexão" (um número).
 *
 * @see https://doc.evolution-api.com/
 */
class EvolutionManager
{
    private ?EvolutionSetting $setting = null;
    private bool $settingLoaded = false;

    public function isConfigured(): bool
    {
        $setting = $this->setting();
        $enabled = $setting ? $setting->enabled : true;

        return $enabled && filled($this->baseUrl()) && filled($this->globalKey());
    }

    /** Testa a conexão com o servidor Evolution (lista instâncias). */
    public function health(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            return $this->request()->get($this->url('/instance/fetchInstances'))->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** URL pública do webhook de recebimento (Fase 2), do banco ou do config. */
    public function webhookUrl(): ?string
    {
        return $this->setting()?->webhook_url ?: config('services.evolution.webhook_url');
    }

    /**
     * Cria a instância na Evolution. Retorna o payload (inclui o token/apikey da instância e,
     * possivelmente, o primeiro QR). $webhookUrl liga o recebimento (Fase 2) se informado.
     */
    public function createInstance(string $instance, ?string $webhookUrl = null): array
    {
        $body = [
            'instanceName' => $instance,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ];

        if (filled($webhookUrl)) {
            $body['webhook'] = [
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => true,
                'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
            ];
        }

        return $this->request()->post($this->url('/instance/create'), $body)->json() ?? [];
    }

    /** Solicita o QR Code / código de pareamento da instância. Retorna { base64, code, pairingCode }. */
    public function connect(string $instance): array
    {
        return $this->request()->get($this->url("/instance/connect/{$instance}"))->json() ?? [];
    }

    /** Estado da conexão: { instance: { state: open|connecting|close } }. */
    public function connectionState(string $instance): array
    {
        return $this->request()->get($this->url("/instance/connectionState/{$instance}"))->json() ?? [];
    }

    /** Desconecta o número (logout) mantendo a instância. */
    public function logout(string $instance): bool
    {
        return $this->request()->delete($this->url("/instance/logout/{$instance}"))->successful();
    }

    /** Exclui a instância por completo na Evolution. */
    public function deleteInstance(string $instance): bool
    {
        return $this->request()->delete($this->url("/instance/delete/{$instance}"))->successful();
    }

    /** Envia texto por uma conexão específica (usa o token da instância, com fallback na chave global). */
    public function sendText(WhatsappConnection $connection, string $number, string $message): bool
    {
        $response = $this->request($connection->token)->post($this->url("/message/sendText/{$connection->instance}"), [
            'number' => $number,
            'text' => $message,
        ]);

        return $response->successful();
    }

    private function request(?string $apiKey = null): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $apiKey ?: $this->globalKey(),
            'Content-Type' => 'application/json',
        ])->acceptJson()->timeout(20);
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->baseUrl(), '/').$path;
    }

    private function baseUrl(): ?string
    {
        return $this->setting()?->base_url ?: config('services.evolution.base_url');
    }

    private function globalKey(): ?string
    {
        return $this->setting()?->api_key ?: config('services.evolution.key');
    }

    /** Linha de config global (memoizada). Pode ser null se ainda não configurada. */
    private function setting(): ?EvolutionSetting
    {
        if (! $this->settingLoaded) {
            $this->setting = EvolutionSetting::first();
            $this->settingLoaded = true;
        }

        return $this->setting;
    }
}
