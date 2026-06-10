<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleDriveService;
use App\Services\StorageService;
use App\Services\Whatsapp\WhatsappMediaCleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function __construct(
        private readonly GoogleDriveService $drive,
        private readonly StorageService $storage,
        private readonly WhatsappMediaCleanupService $cleanup,
    ) {}

    public function show(): Response
    {
        $tenant = app('tenant');
        $setting = $tenant->driveSetting;

        $driveUsage = null;
        if ($setting?->isActive()) {
            $about = $this->drive->about($setting);
            if ($about) {
                $driveUsage = ['limit' => $about['limit'] ?? null, 'usage' => $about['usage'] ?? null];
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
            'usage' => $driveUsage,
            'planUsage' => $this->storage->getUsageStats($tenant),
            'cleanup' => $tenant->whatsappCleanupPolicy(),
        ]);
    }

    /** Salva a política de limpeza automática de mídia de WhatsApp (em tenants.settings). */
    public function updateCleanup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:off,date,quota',
            'retention_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $tenant = app('tenant');
        $settings = $tenant->settings ?? [];

        data_set($settings, 'whatsapp_media_cleanup', [
            'mode' => $data['mode'],
            'retention_days' => $data['mode'] === 'date' ? ($data['retention_days'] ?? 90) : null,
        ]);

        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Política de limpeza salva.');
    }

    /** Libera espaço apagando a mídia mais antiga do WhatsApp (25%/50%/100% do volume na plataforma). */
    public function freeSpace(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fraction' => 'required|numeric|in:0.25,0.5,1',
        ]);

        $freed = $this->cleanup->freeFraction(app('tenant'), (float) $data['fraction']);

        return back()->with('success', $freed > 0
            ? "Espaço liberado: {$freed} MB de mídia do WhatsApp foram removidos do sistema."
            : 'Não havia mídia do WhatsApp na plataforma para remover.');
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
