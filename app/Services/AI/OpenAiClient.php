<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class OpenAiClient implements AiProviderClient
{
    public function __construct(private readonly AiSettingsManager $settings) {}

    public function complete(string $system, array $messages, int $maxTokens = 4096): string
    {
        $response = Http::baseUrl($this->settings->baseUrl())
            ->withToken($this->settings->apiKey())
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->post('/responses', [
                'model' => $this->settings->model(),
                'instructions' => $system,
                'input' => $this->mapMessages($messages),
                'max_output_tokens' => $maxTokens,
                'store' => false,
            ]);

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message', 'Falha na chamada a OpenAI.');
            throw new AiException($message);
        }

        $text = $this->extractText($response->json() ?? []);

        if ($text === '') {
            throw new AiException('A OpenAI nao retornou texto na resposta.');
        }

        return $text;
    }

    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     */
    private function mapMessages(array $messages): array
    {
        return array_map(fn ($message) => [
            'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $message['content'],
        ], $messages);
    }

    private function extractText(array $payload): string
    {
        $direct = (string) data_get($payload, 'output_text', '');
        if (trim($direct) !== '') {
            return trim($direct);
        }

        $parts = [];
        foreach (data_get($payload, 'output', []) as $item) {
            foreach (data_get($item, 'content', []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && filled($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
