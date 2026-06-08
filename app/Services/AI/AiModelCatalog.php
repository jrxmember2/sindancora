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
                $this->option('claude-opus-4-7', 'Claude Opus 4.7', 'Geracao anterior do Opus 4.8.'),
                $this->option('claude-opus-4-6', 'Claude Opus 4.6', 'Geracao anterior do Opus 4.7.'),
                $this->option('claude-sonnet-4-6', 'Claude Sonnet 4.6', 'Melhor equilibrio entre inteligencia, velocidade e custo.', true),
                $this->option('claude-opus-4-5', 'Claude Opus 4.5', 'Geracao anterior do Opus 4.6.'),
                $this->option('claude-sonnet-4-5', 'Claude Sonnet 4.5', 'Geracao anterior do Sonnet 4.'),
                $this->option('claude-haiku-4-5', 'Claude Haiku 4.5', 'Alias atual do Haiku 4.5.'),
                $this->option('claude-haiku-4-5-20251001', 'Claude Haiku 4.5 20251001', 'Snapshot fixo do Haiku 4.5.'),
            ],
            AiSetting::PROVIDER_OPENAI => [
                $this->option('gpt-5.5', 'GPT-5.5', 'Modelo flagship para raciocinio complexo e codigo.', true),
                $this->option('gpt-5.5-pro', 'GPT-5.5 Pro', 'Mais preciso, mas pode levar varios minutos em chamadas dificeis.'),
                $this->option('gpt-5.4', 'GPT-5.4', 'Modelo frontier mais acessivel que GPT-5.5.'),
                $this->option('gpt-5.4-pro', 'GPT-5.4 Pro', 'Mais preciso que GPT-5.4, com latencia alta.'),
                $this->option('gpt-5.4-mini', 'GPT-5.4 mini', 'Baixa latencia e custo menor para alto volume.'),
                $this->option('gpt-5.4-nano', 'GPT-5.4 nano', 'Opcao mais economica da familia GPT-5.4.'),
                $this->option('chat-latest', 'Chat Latest', 'Modelo instantaneo mais recente usado no ChatGPT.'),
                $this->option('gpt-5.3-codex', 'GPT-5.3-Codex', 'Modelo atual para tarefas agenticas de codigo.'),
                $this->option('gpt-5.2', 'GPT-5.2', 'Modelo frontier anterior para trabalho profissional.'),
                $this->option('gpt-5.2-pro', 'GPT-5.2 Pro', 'Versao Pro anterior para respostas mais precisas.'),
                $this->option('gpt-5.1', 'GPT-5.1', 'Modelo GPT-5 anterior para codigo e tarefas agenticas.'),
                $this->option('gpt-5', 'GPT-5', 'Modelo GPT-5 anterior com esforco de raciocinio configuravel.'),
                $this->option('gpt-5-pro', 'GPT-5 Pro', 'Versao Pro do GPT-5.'),
                $this->option('gpt-5-mini', 'GPT-5 mini', 'Opcao GPT-5 menor e mais rapida.'),
                $this->option('gpt-5-nano', 'GPT-5 nano', 'Opcao GPT-5 de menor custo.'),
                $this->option('o3-pro', 'o3-pro', 'Versao do o3 com mais computacao para respostas melhores.'),
                $this->option('o3', 'o3', 'Modelo de raciocinio para tarefas complexas.'),
                $this->option('gpt-4.1', 'GPT-4.1', 'Modelo GPT-4.1 para texto e visao.'),
                $this->option('gpt-4.1-mini', 'GPT-4.1 mini', 'Opcao GPT-4.1 de menor custo.'),
                $this->option('gpt-4o-mini', 'GPT-4o mini', 'Modelo multimodal anterior de baixo custo.'),
            ],
            AiSetting::PROVIDER_GEMINI => [
                $this->option('gemini-3.5-flash', 'Gemini 3.5 Flash', 'Modelo estavel atual para tarefas agenticas e codigo.', true),
                $this->option('gemini-3.1-pro-preview', 'Gemini 3.1 Pro Preview', 'Preview para tarefas complexas e fluxos agenticos.'),
                $this->option('gemini-3.1-pro-preview-customtools', 'Gemini 3.1 Pro Preview Custom Tools', 'Endpoint especializado para fluxos com ferramentas customizadas.'),
                $this->option('gemini-3-flash-preview', 'Gemini 3 Flash Preview', 'Preview rapido da familia Gemini 3.'),
                $this->option('gemini-3.1-flash-lite', 'Gemini 3.1 Flash-Lite', 'Opcao estavel leve da familia Gemini 3.'),
                $this->option('gemini-2.5-pro', 'Gemini 2.5 Pro', 'Modelo 2.5 para raciocinio complexo.'),
                $this->option('gemini-2.5-flash', 'Gemini 2.5 Flash', 'Bom custo-beneficio para alto volume.'),
                $this->option('gemini-2.5-flash-lite', 'Gemini 2.5 Flash-Lite', 'Mais rapido e economico da familia 2.5.'),
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
