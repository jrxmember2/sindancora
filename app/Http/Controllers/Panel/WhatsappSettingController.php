<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\TenantWhatsappSetting;
use App\Services\Whatsapp\WhatsAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappSettingController extends Controller
{
    public function edit(): Response
    {
        $setting = $this->setting();

        return Inertia::render('Settings/Whatsapp', [
            'setting' => [
                'base_url' => $setting->base_url,
                'instance' => $setting->instance,
                'enabled' => $setting->enabled,
                'has_api_key' => filled($setting->api_key),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = $this->setting();

        $data = $request->validate([
            'base_url' => 'nullable|url|max:500',
            'instance' => 'nullable|string|max:100',
            'api_key' => 'nullable|string|max:255',
            'enabled' => 'boolean',
        ]);

        $setting->base_url = $data['base_url'] ?? null;
        $setting->instance = $data['instance'] ?? null;
        $setting->enabled = (bool) ($data['enabled'] ?? false);

        // Só sobrescreve a chave quando uma nova é enviada (campo write-only).
        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        $setting->save();

        return back()->with('success', 'Configuração de WhatsApp salva.');
    }

    /** Verifica a conexão da instância na Evolution API. */
    public function test(): RedirectResponse
    {
        $setting = $this->setting();

        if (! $setting->isUsable()) {
            return back()->with('error', 'Preencha URL, instância e chave (e ative) antes de testar.');
        }

        try {
            $state = (new WhatsAppClient($setting))->connectionState();
            $status = data_get($state, 'instance.state', data_get($state, 'state', 'desconhecido'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Falha na conexão: '.$e->getMessage());
        }

        return back()->with('success', "Conexão OK. Estado da instância: {$status}.");
    }

    private function setting(): TenantWhatsappSetting
    {
        $tenant = app('tenant');

        return TenantWhatsappSetting::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['enabled' => false],
        );
    }
}
