<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente do Firebase Cloud Messaging (HTTP v1) sem dependências externas: assina o JWT da conta
 * de serviço com OpenSSL (RS256), troca por um access token (cacheado) e envia mensagens data-only.
 *
 * @see https://firebase.google.com/docs/cloud-messaging/migrate-v1
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    /** Resultado do envio para um token. */
    public const OK = 'ok';

    public const INVALID = 'invalid'; // token morto → remover localmente

    public const ERROR = 'error';

    public function isConfigured(): bool
    {
        return filled($this->projectId()) && $this->credentials() !== null;
    }

    /**
     * Envia uma mensagem data-only para um token. O app constrói a notificação a partir do `data`.
     *
     * @param  array<string,string>  $data
     */
    public function send(string $token, array $data): string
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return self::ERROR;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", [
                'message' => [
                    'token' => $token,
                    'data' => $data,
                    'android' => ['priority' => 'high'],
                ],
            ]);

        if ($response->successful()) {
            return self::OK;
        }

        // Token inexistente/desregistrado → sinaliza para remoção.
        $status = $response->json('error.status');
        if ($response->status() === 404 || in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
            return self::INVALID;
        }

        Log::warning('FCM: falha ao enviar push', ['status' => $response->status(), 'error' => $response->json('error.status')]);

        return self::ERROR;
    }

    /** Access token OAuth2 da conta de serviço (cacheado ~55 min). */
    private function accessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () {
            $creds = $this->credentials();
            if (! $creds) {
                return null;
            }

            $now = time();
            $claims = [
                'iss' => $creds['client_email'],
                'scope' => self::SCOPE,
                'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $jwt = $this->signJwt($claims, $creds['private_key']);
            if (! $jwt) {
                return null;
            }

            $response = Http::asForm()->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::warning('FCM: falha ao obter access token', ['status' => $response->status()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /** Monta e assina (RS256) o JWT da conta de serviço. */
    private function signJwt(array $claims, string $privateKey): ?string
    {
        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('FCM: falha ao assinar JWT (private_key inválida?).');

            return null;
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function projectId(): ?string
    {
        return config('services.fcm.project_id');
    }

    /** @return array{client_email:string,private_key:string,token_uri?:string}|null */
    private function credentials(): ?array
    {
        $json = config('services.fcm.credentials_json');

        if (blank($json) && filled(config('services.fcm.credentials_file'))) {
            $path = config('services.fcm.credentials_file');
            $json = is_readable($path) ? file_get_contents($path) : null;
        }

        if (blank($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return (is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'])) ? $decoded : null;
    }
}
