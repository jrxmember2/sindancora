<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP fino para a Messages API da Anthropic.
 */
class ClaudeClient implements AiProviderClient
{
    public function __construct(private readonly AiSettingsManager $settings) {}

    public function configured(): bool
    {
        return $this->settings->provider() === AiSetting::PROVIDER_ANTHROPIC
            && $this->settings->isConfigured();
    }

    /**
     * Envia uma conversa e devolve o texto da resposta.
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     */
    public function complete(string $system, array $messages, int $maxTokens = 4096): string
    {
        if (! $this->configured()) {
            throw new AiException('A integracao global de IA nao esta configurada. Configure provedor, modelo e chave em Admin > IA.');
        }

        $response = Http::baseUrl($this->settings->baseUrl())
            ->withHeaders([
                'x-api-key' => $this->settings->apiKey(),
                'anthropic-version' => config('services.anthropic.version'),
                'content-type' => 'application/json',
            ])
            ->timeout(60)
            ->post('/messages', [
                'model' => $this->settings->model(),
                'max_tokens' => $maxTokens,
                'system' => [[
                    'type' => 'text',
                    'text' => $system,
                    'cache_control' => ['type' => 'ephemeral'],
                ]],
                'messages' => array_map(fn ($m) => [
                    'role' => $m['role'],
                    'content' => $m['content'],
                ], $messages),
            ]);

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message', 'Falha na chamada a IA.');
            throw new AiException($message);
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return trim($text);
    }
}
