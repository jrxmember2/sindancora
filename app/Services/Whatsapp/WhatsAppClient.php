<?php

namespace App\Services\Whatsapp;

use App\Models\TenantWhatsappSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP fino para a Evolution API (WhatsApp). Amarrado a um TenantWhatsappSetting.
 *
 * @see https://doc.evolution-api.com/
 */
class WhatsAppClient
{
    public function __construct(private readonly TenantWhatsappSetting $setting) {}

    /** Envia uma mensagem de texto para um número (somente dígitos, com DDI). */
    public function sendText(string $number, string $message): bool
    {
        $response = $this->request()->post($this->setting->sendTextUrl(), [
            'number' => $number,
            'text' => $message,
        ]);

        return $response->successful();
    }

    /** Estado da conexão da instância (para o "testar conexão"). */
    public function connectionState(): array
    {
        return $this->request()->get($this->setting->connectionStateUrl())->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->setting->api_key,
            'Content-Type' => 'application/json',
        ])->acceptJson()->timeout(15);
    }
}
