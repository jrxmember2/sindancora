<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenAiClient implements AiProviderClient
{
    public function __construct(private readonly AiSettingsManager $settings) {}

    public function complete(string $system, array $messages, int $maxTokens = 4096): string
    {
        $model = (string) $this->settings->model();
        $payload = [
            'model' => $model,
            'instructions' => $system,
            'input' => $this->mapMessages($messages),
            'max_output_tokens' => $maxTokens,
            'store' => false,
        ];

        $reasoningEffort = $this->reasoningEffort($model);
        if ($reasoningEffort) {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        } else {
            // Modelos de reasoning (gpt-5/o-series) não aceitam temperature/top_p; só envia nos demais.
            $payload['temperature'] = $this->settings->temperature();
            if ($this->settings->topP() !== null) {
                $payload['top_p'] = $this->settings->topP();
            }
        }

        try {
            $response = Http::baseUrl($this->settings->baseUrl())
                ->withToken($this->settings->apiKey())
                ->acceptJson()
                ->asJson()
                ->timeout(60)
                ->post('/responses', $payload);
        } catch (ConnectionException $e) {
            throw new AiException("Nao foi possivel conectar a OpenAI para o modelo {$model}: {$e->getMessage()}");
        }

        if ($response->failed()) {
            throw new AiException($this->errorMessage($response, $model));
        }

        $text = $this->extractText($response->json() ?? []);

        if ($text === '') {
            throw new AiException("A OpenAI respondeu, mas nao retornou texto para o modelo {$model}.");
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

    private function reasoningEffort(string $model): ?string
    {
        if ($model === 'gpt-5-pro') {
            return 'high';
        }

        if (str_starts_with($model, 'gpt-5') && str_ends_with($model, '-pro')) {
            return 'medium';
        }

        if ($model === 'o3-pro') {
            return null;
        }

        if (str_starts_with($model, 'gpt-5') || preg_match('/^o[1-9]/', $model) === 1) {
            return 'low';
        }

        return null;
    }

    private function errorMessage(Response $response, string $model): string
    {
        $status = $response->status();
        $message = (string) data_get($response->json(), 'error.message', 'Falha na chamada a OpenAI.');
        $requestId = $response->header('x-request-id')
            ?: $response->header('request-id')
            ?: data_get($response->json(), 'request_id');

        $prefix = "OpenAI retornou HTTP {$status} para o modelo {$model}.";

        $detail = match (true) {
            $status === 401 => 'Chave invalida ou sem permissao. Gere uma nova chave no projeto correto em platform.openai.com/api-keys.',
            $status === 403 => 'A chave nao tem acesso ao modelo, ao projeto ou a organizacao selecionada.',
            $status === 404 => 'Modelo nao encontrado ou indisponivel para esta conta. Escolha um modelo do dropdown ou confirme acesso ao modelo.',
            $status === 429 => 'Limite de uso, cota ou rate limit atingido. Confira billing e limites do projeto na OpenAI.',
            $status >= 500 => "Erro temporario da OpenAI: {$message}",
            default => $message,
        };

        return trim($prefix.' '.$detail.($requestId ? " Request ID: {$requestId}." : ''));
    }
}
