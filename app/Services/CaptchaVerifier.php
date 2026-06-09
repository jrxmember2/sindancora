<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Verificação de captcha Cloudflare Turnstile para os formulários públicos. É opcional: quando o
 * secret não está configurado, a verificação é ignorada (no-op) para não bloquear ambientes sem
 * chaves. Quando configurado, valida o token contra a API da Cloudflare.
 */
class CaptchaVerifier
{
    /** Indica se o captcha está ativo (chaves configuradas). */
    public function enabled(): bool
    {
        return ! empty(config('services.turnstile.secret')) && ! empty(config('services.turnstile.site_key'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /** Valida o token enviado pelo widget. Sem captcha ativo, sempre passa. */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(config('services.turnstile.verify_url'), array_filter([
                'secret' => config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return (bool) $response->json('success', false);
        } catch (\Throwable) {
            // Falha de rede não deve derrubar o formulário; o honeypot e o rate-limit seguem ativos.
            return true;
        }
    }
}
