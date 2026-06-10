<?php

namespace App\Services\Google;

use App\Models\TenantDriveSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cliente do Google Drive via REST (sem google/apiclient — mesmo estilo Http:: do EvolutionManager).
 * App OAuth global da plataforma; cada tenant conecta a própria conta Google (escopo drive.file) e a
 * mídia é gravada numa pasta do Drive DELE. Os arquivos são de responsabilidade do tenant.
 *
 * @see https://developers.google.com/drive/api/reference/rest/v3
 */
class GoogleDriveService
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

    /** Access tokens memoizados por setting (id => token) dentro do request. */
    private array $accessTokens = [];

    public function isConfigured(): bool
    {
        return filled(config('services.google_drive.client_id'))
            && filled(config('services.google_drive.client_secret'))
            && filled(config('services.google_drive.redirect_uri'));
    }

    /** URL de consentimento OAuth. `$state` carrega o tenant assinado (validado no callback). */
    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',          // força devolver refresh_token
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Troca o `code` do callback por tokens. Retorna ['refresh_token','access_token','email'] ou
     * lança RuntimeException. O refresh_token só vem com access_type=offline + prompt=consent.
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if (! $response->successful()) {
            Log::warning('Google Drive: troca de code falhou', ['body' => mb_substr($response->body(), 0, 500)]);
            throw new RuntimeException('Falha ao autorizar a conta Google.');
        }

        $accessToken = $response->json('access_token');
        $refreshToken = $response->json('refresh_token');

        if (blank($refreshToken)) {
            // Consentimento sem refresh_token (já concedido antes sem revogar) → orienta reconectar.
            throw new RuntimeException('O Google não retornou autorização permanente. Remova o acesso do app na conta Google e conecte novamente.');
        }

        return [
            'refresh_token' => $refreshToken,
            'access_token' => $accessToken,
            'email' => $this->fetchEmail($accessToken),
        ];
    }

    /** Garante a pasta raiz da mídia no Drive do tenant; persiste e retorna o folder id. */
    public function ensureRootFolder(TenantDriveSetting $setting): string
    {
        if (filled($setting->root_folder_id)) {
            return $setting->root_folder_id;
        }

        $token = $this->accessTokenFor($setting);
        $response = Http::withToken($token)->post(self::API.'/files?fields=id', [
            'name' => 'SindÂncora — Mídia WhatsApp',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if (! $response->successful() || blank($response->json('id'))) {
            throw new RuntimeException('Não foi possível criar a pasta no Google Drive.');
        }

        $folderId = $response->json('id');
        $setting->forceFill(['root_folder_id' => $folderId])->save();

        return $folderId;
    }

    /** Sobe um arquivo (multipart/related, atômico) para a pasta do tenant. Retorna o file id. */
    public function upload(TenantDriveSetting $setting, string $contents, string $filename, ?string $mime): string
    {
        $token = $this->accessTokenFor($setting);
        $folderId = $this->ensureRootFolder($setting);
        $mime = $mime ?: 'application/octet-stream';

        $boundary = 'sindancora'.Str::random(24);
        $metadata = json_encode(['name' => $filename, 'parents' => [$folderId]]);

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadata."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mime}\r\n\r\n"
            .$contents."\r\n"
            ."--{$boundary}--";

        $response = Http::withToken($token)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD.'?uploadType=multipart&fields=id');

        if (! $response->successful() || blank($response->json('id'))) {
            Log::warning('Google Drive: upload falhou', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);
            throw new RuntimeException('Falha ao enviar o arquivo para o Google Drive.');
        }

        return $response->json('id');
    }

    /** Baixa os bytes de um arquivo do Drive (≤ limite de mídia do WhatsApp). */
    public function download(TenantDriveSetting $setting, string $fileId): string
    {
        $token = $this->accessTokenFor($setting);
        $response = Http::withToken($token)->get(self::API."/files/{$fileId}?alt=media");

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao baixar o arquivo do Google Drive.');
        }

        return $response->body();
    }

    /** Apaga um arquivo do Drive. Best-effort. */
    public function delete(TenantDriveSetting $setting, string $fileId): bool
    {
        try {
            $token = $this->accessTokenFor($setting);

            return Http::withToken($token)->delete(self::API."/files/{$fileId}")->successful();
        } catch (\Throwable $e) {
            Log::warning('Google Drive: delete falhou', ['file' => $fileId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Cota/uso do Drive do tenant para exibir na UI. Retorna ['limit','usage','email'] ou []. */
    public function about(TenantDriveSetting $setting): array
    {
        try {
            $token = $this->accessTokenFor($setting);
            $response = Http::withToken($token)->get(self::API.'/about?fields=storageQuota,user');

            if (! $response->successful()) {
                return [];
            }

            return [
                'limit' => $response->json('storageQuota.limit'),
                'usage' => $response->json('storageQuota.usage'),
                'email' => $response->json('user.emailAddress'),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Access token a partir do refresh_token (memoizado por setting). Em falha de refresh, marca o
     * setting como `error` e lança — o chamador decide o fallback.
     */
    public function accessTokenFor(TenantDriveSetting $setting): string
    {
        if (isset($this->accessTokens[$setting->id])) {
            return $this->accessTokens[$setting->id];
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'refresh_token' => $setting->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            $setting->forceFill([
                'status' => 'error',
                'last_error' => 'Falha ao renovar o acesso ao Google Drive. Reconecte a conta.',
            ])->save();

            throw new RuntimeException('Falha ao renovar o acesso ao Google Drive.');
        }

        return $this->accessTokens[$setting->id] = $response->json('access_token');
    }

    /** E-mail da conta conectada (via Drive about). */
    private function fetchEmail(?string $accessToken): ?string
    {
        if (blank($accessToken)) {
            return null;
        }

        try {
            $response = Http::withToken($accessToken)->get(self::API.'/about?fields=user');

            return $response->successful() ? $response->json('user.emailAddress') : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
