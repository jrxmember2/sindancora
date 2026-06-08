<?php

namespace App\Services\AI;

use App\Models\AiSetting;

class AiModelCatalog
{
    /**
     * Modelos de texto/chat suportados pelos clientes HTTP atuais da plataforma.
     */
    public function options(): array
    {
        return [
            AiSetting::PROVIDER_ANTHROPIC => [
                $this->option('claude-opus-4-8', 'Claude Opus 4.8', 'Mais capaz para raciocinio complexo e tarefas longas.'),
                $this->option('claude-sonnet-4-6', 'Claude Sonnet 4.6', 'Melhor equilibrio entre inteligencia, velocidade e custo.', true),
                $this->option('claude-haiku-4-5', 'Claude Haiku 4.5', 'Mais rapido para rotinas objetivas e menor custo.'),
                $this->option('claude-haiku-4-5-20251001', 'Claude Haiku 4.5 20251001', 'Snapshot fixo do Haiku 4.5.'),
                $this->option('claude-sonnet-4-5', 'Claude Sonnet 4.5', 'Geracao anterior do Sonnet 4.'),
                $this->option('claude-opus-4-6', 'Claude Opus 4.6', 'Snapshot anterior do Opus 4.'),
                $this->option('claude-opus-4-7', 'Claude Opus 4.7', 'Snapshot anterior do Opus 4.'),
                $this->option('claude-3-7-sonnet-latest', 'Claude 3.7 Sonnet latest', 'Alias legado de Sonnet 3.7.'),
                $this->option('claude-3-5-sonnet-latest', 'Claude 3.5 Sonnet latest', 'Alias legado de Sonnet 3.5.'),
                $this->option('claude-3-5-haiku-latest', 'Claude 3.5 Haiku latest', 'Alias legado de Haiku 3.5.'),
            ],
            AiSetting::PROVIDER_OPENAI => [
                $this->option('gpt-5.5', 'GPT-5.5', 'Modelo flagship para raciocinio complexo e codigo.', true),
                $this->option('gpt-5.5-pro', 'GPT-5.5 Pro', 'Mais preciso, mas pode levar varios minutos em chamadas dificeis.'),
                $this->option('gpt-5.4', 'GPT-5.4', 'Modelo frontier mais acessivel que GPT-5.5.'),
                $this->option('gpt-5.4-pro', 'GPT-5.4 Pro', 'Mais preciso que GPT-5.4, com latencia alta.'),
                $this->option('gpt-5.4-mini', 'GPT-5.4 mini', 'Baixa latencia e custo menor para alto volume.'),
                $this->option('gpt-5.4-nano', 'GPT-5.4 nano', 'Opcao mais economica da familia GPT-5.4.'),
                $this->option('gpt-5', 'GPT-5', 'Modelo GPT-5 base.'),
                $this->option('gpt-5-mini', 'GPT-5 mini', 'Opcao GPT-5 menor e mais rapida.'),
                $this->option('gpt-5-nano', 'GPT-5 nano', 'Opcao GPT-5 de menor custo.'),
                $this->option('gpt-4.1', 'GPT-4.1', 'Modelo GPT-4.1 para texto e visao.'),
                $this->option('gpt-4.1-mini', 'GPT-4.1 mini', 'Opcao GPT-4.1 de menor custo.'),
                $this->option('gpt-4.1-nano', 'GPT-4.1 nano', 'Opcao GPT-4.1 economica.'),
                $this->option('gpt-4o', 'GPT-4o', 'Modelo multimodal anterior, compativel com texto.'),
                $this->option('gpt-4o-mini', 'GPT-4o mini', 'Modelo multimodal anterior de baixo custo.'),
                $this->option('o3', 'o3', 'Modelo de raciocinio.'),
                $this->option('o4-mini', 'o4-mini', 'Modelo de raciocinio com menor custo.'),
            ],
            AiSetting::PROVIDER_GEMINI => [
                $this->option('gemini-3.5-flash', 'Gemini 3.5 Flash', 'Modelo estavel atual para tarefas agenticas e codigo.', true),
                $this->option('gemini-3.1-pro-preview', 'Gemini 3.1 Pro Preview', 'Preview para tarefas complexas e fluxos agenticos.'),
                $this->option('gemini-3-flash-preview', 'Gemini 3 Flash Preview', 'Preview rapido da familia Gemini 3.'),
                $this->option('gemini-3.1-flash-lite', 'Gemini 3.1 Flash-Lite', 'Opcao estavel leve da familia Gemini 3.'),
                $this->option('gemini-2.5-pro', 'Gemini 2.5 Pro', 'Modelo 2.5 para raciocinio complexo.'),
                $this->option('gemini-2.5-flash', 'Gemini 2.5 Flash', 'Bom custo-beneficio para alto volume.'),
                $this->option('gemini-2.5-flash-lite', 'Gemini 2.5 Flash-Lite', 'Mais rapido e economico da familia 2.5.'),
                $this->option('gemini-2.0-flash', 'Gemini 2.0 Flash', 'Modelo 2.0 rapido com janela longa.'),
                $this->option('gemini-2.0-flash-lite', 'Gemini 2.0 Flash-Lite', 'Opcao 2.0 economica.'),
            ],
        ];
    }

    public function isKnown(string $provider, ?string $model): bool
    {
        if (! filled($model)) {
            return false;
        }

        return collect($this->options()[$provider] ?? [])
            ->contains(fn (array $option) => $option['value'] === $model);
    }

    private function option(string $value, string $label, string $description, bool $recommended = false): array
    {
        return compact('value', 'label', 'description', 'recommended');
    }
}
