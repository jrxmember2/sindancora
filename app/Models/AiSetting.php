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
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
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
