<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    use HasUuidKey;

    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_GEMINI = 'gemini';

    protected $fillable = [
        'provider',
        'model',
        'base_url',
        'api_key',
        'enabled',
        'temperature',
        'top_p',
        'max_tokens',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'temperature' => 'float',
            'top_p' => 'float',
            'max_tokens' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Ajustes recomendados por provedor (factual/condominial: temperatura baixa). Usados como
     * pré-configuração na tela de Admin > IA e como fallback quando o valor não está definido.
     *
     * @return array{temperature: float, top_p: float|null, max_tokens: int}
     */
    public static function tuningDefaults(string $provider): array
    {
        // Recomendado: ajustar pela temperatura e deixar top_p desligado (null) para não enviar
        // os dois parâmetros ao mesmo tempo. max_tokens dimensiona o tamanho das respostas do chat.
        return match ($provider) {
            self::PROVIDER_GEMINI => ['temperature' => 0.3, 'top_p' => null, 'max_tokens' => 2048],
            self::PROVIDER_OPENAI => ['temperature' => 0.3, 'top_p' => null, 'max_tokens' => 2048],
            default => ['temperature' => 0.3, 'top_p' => null, 'max_tokens' => 2048],
        };
    }

    public static function providerOptions(): array
    {
        return [
            self::PROVIDER_ANTHROPIC => 'Claude / Anthropic',
            self::PROVIDER_OPENAI => 'OpenAI',
            self::PROVIDER_GEMINI => 'Gemini',
        ];
    }

    public static function defaultProvider(): string
    {
        return self::PROVIDER_ANTHROPIC;
    }

    /** Linha unica de configuracao global. */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'provider' => self::defaultProvider(),
            'model' => config('services.anthropic.model'),
            'base_url' => config('services.anthropic.base_url'),
            'enabled' => true,
        ]);
    }

    public function providerLabel(): string
    {
        return self::providerOptions()[$this->provider] ?? $this->provider;
    }
}
