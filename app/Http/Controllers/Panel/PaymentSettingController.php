<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\TenantPaymentSetting;
use App\Services\Payments\AsaasClient;
use App\Services\Payments\AsaasException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingController extends Controller
{
    /** Tela de configuração da integração Asaas do tenant. */
    public function edit(): Response
    {
        $setting = $this->setting();

        return Inertia::render('Settings/Payments', [
            'setting' => [
                'environment' => $setting->environment,
                'billing_type' => $setting->billing_type,
                'enabled' => $setting->enabled,
                'wallet_id' => $setting->wallet_id,
                'webhook_token' => $setting->webhook_token,
                'has_api_key' => filled($setting->api_key),
            ],
            'webhook_url' => url('/api/webhooks/asaas'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = $this->setting();

        $data = $request->validate([
            'environment' => 'required|in:sandbox,production',
            'enabled' => 'boolean',
            'wallet_id' => 'nullable|string|max:100',
            'api_key' => 'nullable|string|max:255',
            'regenerate_token' => 'boolean',
        ]);

        $setting->environment = $data['environment'];
        $setting->enabled = (bool) ($data['enabled'] ?? false);
        $setting->wallet_id = $data['wallet_id'] ?? null;

        // Só sobrescreve a chave quando uma nova é enviada (campo write-only).
        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        // Gera o token do webhook na primeira vez ou quando solicitado.
        if (blank($setting->webhook_token) || ($data['regenerate_token'] ?? false)) {
            $setting->webhook_token = Str::random(48);
        }

        $setting->save();

        return back()->with('success', 'Configuração de pagamento salva.');
    }

    /** Verifica a credencial chamando GET /myAccount no Asaas. */
    public function test(): RedirectResponse
    {
        $setting = $this->setting();

        if (blank($setting->api_key)) {
            return back()->with('error', 'Configure a chave de API antes de testar.');
        }

        try {
            (new AsaasClient($setting))->myAccount();
        } catch (AsaasException $e) {
            return back()->with('error', 'Falha na conexão: '.$e->getMessage());
        }

        return back()->with('success', 'Conexão com o Asaas bem-sucedida.');
    }

    /** Devolve (criando se preciso) a configuração Asaas do tenant atual. */
    private function setting(): TenantPaymentSetting
    {
        $tenant = app('tenant');

        return TenantPaymentSetting::firstOrCreate(
            ['tenant_id' => $tenant->id, 'provider' => 'asaas'],
            ['environment' => 'sandbox', 'billing_type' => 'UNDEFINED', 'enabled' => false],
        );
    }
}
