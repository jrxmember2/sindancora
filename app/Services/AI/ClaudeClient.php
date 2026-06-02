<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP fino para a Messages API da Anthropic (sem SDK oficial PHP).
 * O prompt de sistema (estável) é marcado para cache; o contexto volátil do RAG vai nas mensagens.
 */
class ClaudeClient
{
    public function configured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Envia uma conversa e devolve o texto da resposta.
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     */
    public function complete(string $system, array $messages, int $maxTokens = 4096): string
    {
        if (! $this->configured()) {
            throw new AiException('A integração de IA não está configurada (defina ANTHROPIC_API_KEY).');
        }

        $response = Http::baseUrl(config('services.anthropic.base_url'))
            ->withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => config('services.anthropic.version'),
                'content-type' => 'application/json',
            ])
            ->timeout(60)
            ->post('/messages', [
                'model' => config('services.anthropic.model'),
                'max_tokens' => $maxTokens,
                // Prompt de sistema estável → cacheado (reduz custo/latência em chamadas repetidas).
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
            $message = data_get($response->json(), 'error.message', 'Falha na chamada à IA.');
            throw new AiException($message);
        }

        // content é um array de blocos; concatena os de texto.
        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return trim($text);
    }
}
