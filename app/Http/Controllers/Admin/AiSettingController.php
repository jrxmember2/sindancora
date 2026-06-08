<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\AI\AiException;
use App\Services\AI\AiProviderManager;
use App\Services\AI\AiSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingController extends Controller
{
    public function __construct(
        private readonly AiSettingsManager $settings,
        private readonly AiProviderManager $provider,
    ) {}

    public function edit(): Response
    {
        $setting = AiSetting::first();
        $provider = $setting?->provider ?: AiSetting::defaultProvider();
        $defaults = $this->settings->defaults();

        return Inertia::render('Admin/AI/Settings', [
            'setting' => [
                'provider' => $provider,
                'model' => $setting?->model ?: data_get($defaults, "{$provider}.model"),
                'base_url' => $setting?->base_url ?: data_get($defaults, "{$provider}.base_url"),
                'enabled' => $setting?->enabled ?? true,
                'has_key' => filled($setting?->api_key) || filled($this->settings->apiKey()),
                'last_checked_at' => $setting?->last_checked_at?->toIso8601String(),
            ],
            'configured' => $this->settings->isConfigured(),
            'runtimeSupported' => $this->settings->runtimeSupported(),
            'providerOptions' => AiSetting::providerOptions(),
            'defaults' => $defaults,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $providers = array_keys(AiSetting::providerOptions());

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in($providers)],
            'model' => 'nullable|string|max:120',
            'base_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:4000',
            'enabled' => 'boolean',
        ]);

        $setting = AiSetting::current();
        $defaults = $this->settings->defaults();
        $provider = $data['provider'];
        $previousProvider = $setting->provider;

        $setting->provider = $provider;
        $setting->model = filled($data['model'] ?? null)
            ? $data['model']
            : data_get($defaults, "{$provider}.model");
        $setting->base_url = filled($data['base_url'] ?? null)
            ? $data['base_url']
            : data_get($defaults, "{$provider}.base_url");
        $setting->enabled = (bool) ($data['enabled'] ?? false);

        // Campo write-only: em branco mantem a chave ja salva apenas se o provedor nao mudou.
        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        } elseif ($previousProvider !== $provider) {
            $setting->api_key = null;
        }

        $setting->save();

        return back()->with('success', 'Configuracao global de IA salva.');
    }

    public function test(): RedirectResponse
    {
        $setting = AiSetting::current();

        try {
            $this->provider->complete(
                'Voce esta testando a conexao de IA da plataforma. Responda apenas OK.',
                [['role' => 'user', 'content' => 'Teste de conexao.']],
                16,
            );
        } catch (AiException $e) {
            return back()->with('error', 'Nao foi possivel conectar a IA: '.$e->getMessage());
        }

        $setting->update(['last_checked_at' => now()]);

        return back()->with('success', 'Conexao com a IA OK.');
    }
}
