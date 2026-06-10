<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Integração de armazenamento externo (Google Drive) por tenant. Conecta a conta Google do tenant
 * (OAuth, escopo drive.file) para descarregar a mídia de WhatsApp no Drive dele. O callback do OAuth
 * é central (GoogleDriveCallbackController) porque o redirect_uri do Google é único.
 */
class DriveIntegrationController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive) {}

    public function show(): Response
    {
        $tenant = app('tenant');
        $setting = $tenant->driveSetting;

        $usage = null;
        if ($setting?->isActive()) {
            $about = $this->drive->about($setting);
            if ($about) {
                $usage = ['limit' => $about['limit'] ?? null, 'usage' => $about['usage'] ?? null];
            }
        }

        return Inertia::render('Settings/Storage', [
            'configured' => $this->drive->isConfigured(),
            'connection' => $setting ? [
                'status' => $setting->status,
                'account_email' => $setting->account_email,
                'connected_at' => $setting->connected_at?->toIso8601String(),
                'last_error' => $setting->last_error,
            ] : null,
            'usage' => $usage,
        ]);
    }

    /** Inicia o consentimento OAuth; o tenant/usuário/retorno viajam num `state` assinado. */
    public function connect(): RedirectResponse
    {
        if (! $this->drive->isConfigured()) {
            return back()->with('error', 'A integração com o Google Drive não está configurada pela plataforma.');
        }

        $state = Crypt::encryptString(json_encode([
            'tenant_id' => app('tenant')->id,
            'user_id' => Auth::id(),
            'return' => route('settings.storage.show'),
            'nonce' => Str::random(16),
        ]));

        return redirect()->away($this->drive->authUrl($state));
    }

    public function disconnect(): RedirectResponse
    {
        $setting = app('tenant')->driveSetting;

        if ($setting) {
            $setting->update([
                'status' => 'disconnected',
                'refresh_token' => null,
                'root_folder_id' => null,
                'account_email' => null,
                'last_error' => null,
            ]);
        }

        return back()->with('success', 'Google Drive desconectado. A mídia já enviada deixa de ser acessível pelo painel.');
    }
}
