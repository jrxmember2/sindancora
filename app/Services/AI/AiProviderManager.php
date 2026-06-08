<?php

namespace App\Services\AI;

use App\Models\AiSetting;

class AiProviderManager
{
    public function __construct(
        private readonly AiSettingsManager $settings,
        private readonly ClaudeClient $claude,
        private readonly OpenAiClient $openAi,
        private readonly GeminiClient $gemini,
    ) {}

    public function configured(): bool
    {
        return $this->settings->isConfigured();
    }

    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     */
    public function complete(string $system, array $messages, int $maxTokens = 4096): string
    {
        if (! $this->configured()) {
            throw new AiException($this->configurationError());
        }

        return $this->client()->complete($system, $messages, $maxTokens);
    }

    private function client(): AiProviderClient
    {
        return match ($this->settings->provider()) {
            AiSetting::PROVIDER_OPENAI => $this->openAi,
            AiSetting::PROVIDER_GEMINI => $this->gemini,
            default => $this->claude,
        };
    }

    private function configurationError(): string
    {
        if (! $this->settings->enabled()) {
            return 'A integracao global de IA esta desabilitada em Admin > IA.';
        }

        if (! $this->settings->runtimeSupported()) {
            return "O provedor {$this->settings->providerLabel()} ainda nao esta ativo para execucao.";
        }

        return 'A integracao global de IA nao esta configurada. Configure provedor, modelo e chave em Admin > IA.';
    }
}
