<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class GeminiClient implements AiProviderClient
{
    public function __construct(private readonly AiSettingsManager $settings) {}

    public function complete(string $system, array $messages, int $maxTokens = 4096): string
    {
        $model = preg_replace('#^models/#', '', (string) $this->settings->model());

        $response = Http::baseUrl($this->settings->baseUrl())
            ->withHeaders([
                'x-goog-api-key' => $this->settings->apiKey(),
                'content-type' => 'application/json',
            ])
            ->acceptJson()
            ->timeout(60)
            ->post('/models/'.$model.':generateContent', [
                'system_instruction' => [
                    'parts' => [['text' => $system]],
                ],
                'contents' => $this->mapMessages($messages),
                'generationConfig' => [
                    'maxOutputTokens' => $maxTokens,
                ],
            ]);

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message', 'Falha na chamada a Gemini.');
            throw new AiException($message);
        }

        $text = $this->extractText($response->json() ?? []);

        if ($text === '') {
            throw new AiException('A Gemini nao retornou texto na resposta.');
        }

        return $text;
    }

    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     */
    private function mapMessages(array $messages): array
    {
        return array_map(fn ($message) => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $message['content']]],
        ], $messages);
    }

    private function extractText(array $payload): string
    {
        $parts = [];
        foreach (data_get($payload, 'candidates.0.content.parts', []) as $part) {
            if (filled($part['text'] ?? null)) {
                $parts[] = $part['text'];
            }
        }

        return trim(implode("\n", $parts));
    }
}
