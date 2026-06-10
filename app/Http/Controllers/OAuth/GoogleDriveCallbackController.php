<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\TenantDriveSetting;
use App\Services\Google\GoogleDriveService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Callback central do OAuth do Google Drive. Chega num domínio fixo (redirect_uri único registrado
 * no Google), fora do ResolveTenant — o tenant é identificado pelo `state` assinado. Troca o code
 * por tokens, persiste o refresh_token (encriptado) e devolve o usuário ao domínio do tenant.
 */
class GoogleDriveCallbackController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $state = $this->decodeState($request->query('state'));
        abort_if($state === null, 403, 'Estado de autorização inválido.');

        $return = $state['return'];

        // Usuário negou o consentimento ou veio sem code.
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->away($this->withStatus($return, 'error'));
        }

        try {
            $tokens = $this->drive->exchangeCode($request->query('code'));
        } catch (\Throwable $e) {
            Log::warning('Google Drive: callback falhou', ['error' => $e->getMessage()]);

            return redirect()->away($this->withStatus($return, 'error'));
        }

        $setting = TenantDriveSetting::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $state['tenant_id']],
            [
                'provider' => 'google_drive',
                'refresh_token' => $tokens['refresh_token'],
                'account_email' => $tokens['email'],
                'status' => 'connected',
                'connected_by' => $state['user_id'] ?? null,
                'connected_at' => now(),
                'last_error' => null,
                'root_folder_id' => null,
            ],
        );

        // Cria a pasta agora para o primeiro upload já encontrar tudo pronto (best-effort).
        try {
            $this->drive->ensureRootFolder($setting);
        } catch (\Throwable $e) {
            Log::warning('Google Drive: criar pasta no callback falhou (será criada no 1º upload)', ['error' => $e->getMessage()]);
        }

        return redirect()->away($this->withStatus($return, 'connected'));
    }

    /** Decifra e valida o `state`. Retorna ['tenant_id','user_id','return'] ou null. */
    private function decodeState(?string $state): ?array
    {
        if (blank($state)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || blank($data['tenant_id'] ?? null) || blank($data['return'] ?? null)) {
            return null;
        }

        return $data;
    }

    private function withStatus(string $url, string $status): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'drive_status='.$status;
    }
}
