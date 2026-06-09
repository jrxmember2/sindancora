<?php

namespace App\Services\AI;

use App\Models\AiSetting;

class AiSettingsManager
{
    private ?AiSetting $setting = null;
    private bool $settingLoaded = false;

    public function setting(): ?AiSetting
    {
        if (! $this->settingLoaded) {
            $this->setting = AiSetting::first();
            $this->settingLoaded = true;
        }

        return $this->setting;
    }

    public function provider(): string
    {
        return $this->setting()?->provider ?: AiSetting::defaultProvider();
    }

    public function providerLabel(): string
    {
        return AiSetting::providerOptions()[$this->provider()] ?? $this->provider();
    }

    public function model(): ?string
    {
        $setting = $this->setting();

        if ($setting && filled($setting->model)) {
            return $setting->model;
        }

        return match ($this->provider()) {
            AiSetting::PROVIDER_OPENAI => config('services.openai.model'),
            AiSetting::PROVIDER_GEMINI => config('services.gemini.model'),
            default => config('services.anthropic.model'),
        };
    }

    public function baseUrl(): ?string
    {
        $setting = $this->setting();

        if ($setting && filled($setting->base_url)) {
            return $setting->base_url;
        }

        return match ($this->provider()) {
            AiSetting::PROVIDER_OPENAI => config('services.openai.base_url'),
            AiSetting::PROVIDER_GEMINI => config('services.gemini.base_url'),
            default => config('services.anthropic.base_url'),
        };
    }

    public function apiKey(): ?string
    {
        $setting = $this->setting();

        if ($setting && filled($setting->api_key)) {
            return $setting->api_key;
        }

        return match ($this->provider()) {
            AiSetting::PROVIDER_OPENAI => config('services.openai.key'),
            AiSetting::PROVIDER_GEMINI => config('services.gemini.key'),
            default => config('services.anthropic.key'),
        };
    }

    public function enabled(): bool
    {
        return $this->setting()?->enabled ?? true;
    }

    public function temperature(): float
    {
        $value = $this->setting()?->temperature;

        return $value !== null ? (float) $value : AiSetting::tuningDefaults($this->provider())['temperature'];
    }

    public function topP(): ?float
    {
        $value = $this->setting()?->top_p;

        return $value !== null ? (float) $value : null;
    }

    public function maxTokens(): int
    {
        $value = $this->setting()?->max_tokens;

        return $value && $value > 0 ? (int) $value : AiSetting::tuningDefaults($this->provider())['max_tokens'];
    }

    public function runtimeSupported(): bool
    {
        return in_array($this->provider(), array_keys(AiSetting::providerOptions()), true);
    }

    public function isConfigured(): bool
    {
        return $this->enabled()
            && $this->runtimeSupported()
            && filled($this->apiKey())
            && filled($this->model())
            && filled($this->baseUrl());
    }

    public function defaults(): array
    {
        return [
            AiSetting::PROVIDER_ANTHROPIC => [
                'model' => config('services.anthropic.model'),
                'base_url' => config('services.anthropic.base_url'),
            ],
            AiSetting::PROVIDER_OPENAI => [
                'model' => config('services.openai.model'),
                'base_url' => config('services.openai.base_url'),
            ],
            AiSetting::PROVIDER_GEMINI => [
                'model' => config('services.gemini.model'),
                'base_url' => config('services.gemini.base_url'),
            ],
        ];
    }
}
