<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvolutionSetting;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuração GLOBAL do servidor Evolution API (super admin). Define a URL base e a chave
 * global usadas por toda a plataforma para criar instâncias, parear números e enviar/receber.
 */
class EvolutionSettingController extends Controller
{
    public function __construct(private readonly EvolutionManager $evolution) {}

    public function edit(): Response
    {
        $setting = EvolutionSetting::first();

        return Inertia::render('Admin/Evolution/Settings', [
            'setting' => [
                'base_url' => $setting?->base_url,
                'webhook_url' => $setting?->webhook_url,
                'enabled' => $setting?->enabled ?? true,
                'has_key' => filled($setting?->api_key),
                'last_checked_at' => $setting?->last_checked_at?->toIso8601String(),
            ],
            'configured' => $this->evolution->isConfigured(),
            // URL protegida (base + segredo) que deve ser registrada na Evolution.
            'webhook_registration_url' => filled($setting?->webhook_url) ? $this->evolution->registrationWebhookUrl() : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'webhook_url' => 'nullable|url|max:255',
            'enabled' => 'boolean',
        ]);

        $setting = EvolutionSetting::current();

        $setting->base_url = $data['base_url'] ?? null;
        $setting->webhook_url = $data['webhook_url'] ?? null;
        $setting->enabled = (bool) ($data['enabled'] ?? false);

        // Chave em branco = mantém a atual (campo write-only).
        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        $setting->save();

        return back()->with('success', 'Configuração do Evolution salva.');
    }

    public function test(): RedirectResponse
    {
        $ok = $this->evolution->health();

        if ($setting = EvolutionSetting::first()) {
            $setting->update(['last_checked_at' => now()]);
        }

        return back()->with($ok ? 'success' : 'error', $ok
            ? 'Conexão com o servidor Evolution OK.'
            : 'Não foi possível conectar ao servidor Evolution. Verifique URL e chave.');
    }

    /** Re-registra o webhook (com segredo) em todas as instâncias já criadas. */
    public function resyncWebhooks(): RedirectResponse
    {
        $url = $this->evolution->registrationWebhookUrl();

        if (blank($url)) {
            return back()->with('error', 'Configure a URL do webhook antes de re-sincronizar.');
        }

        $connections = WhatsappConnection::withoutGlobalScope('tenant')->get(['id', 'instance']);
        $ok = 0;
        foreach ($connections as $connection) {
            if ($this->evolution->setWebhook($connection->instance, $url)) {
                $ok++;
            }
        }

        return back()->with('success', "Webhooks re-sincronizados em {$ok} de {$connections->count()} conexões.");
    }
}
