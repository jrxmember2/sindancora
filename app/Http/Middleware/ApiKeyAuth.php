<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica requisições da API pública por API Key (Authorization: Bearer sk_...).
 * O tenant é resolvido pelo domínio (ResolveTenant) e a chave precisa pertencer a ele.
 * Aplica rate limit por chave e por tenant.
 */
class ApiKeyAuth
{
    private const PER_KEY_PER_MINUTE = 120;
    private const PER_TENANT_PER_MINUTE = 600;

    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $this->extractKey($request);

        if (! $plaintext || ! str_starts_with($plaintext, 'sk_')) {
            return $this->error('UNAUTHENTICATED', 'API Key ausente ou inválida.', 401);
        }

        $key = ApiKey::where('key_hash', ApiKey::hashKey($plaintext))->first();

        if (! $key || ! $key->isActive()) {
            return $this->error('UNAUTHENTICATED', 'API Key inválida, expirada ou revogada.', 401);
        }

        // O tenant é resolvido pelo host; a chave precisa ser do mesmo tenant.
        $tenantId = app()->bound('tenant_id') ? app('tenant_id') : null;
        if (! $tenantId || $key->tenant_id !== $tenantId) {
            return $this->error('FORBIDDEN', 'Esta API Key não pertence a este domínio.', 403);
        }

        if ($response = $this->enforceRateLimit($key)) {
            return $response;
        }

        app()->instance('api_key', $key);
        $key->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        return $bearer ?: null;
    }

    /** Limita por chave e por tenant; retorna resposta 429 quando estourar. */
    private function enforceRateLimit(ApiKey $key): ?JsonResponse
    {
        $buckets = [
            ['api_key:'.$key->id, self::PER_KEY_PER_MINUTE],
            ['api_tenant:'.$key->tenant_id, self::PER_TENANT_PER_MINUTE],
        ];

        foreach ($buckets as [$bucket, $max]) {
            if (RateLimiter::tooManyAttempts($bucket, $max)) {
                $retry = RateLimiter::availableIn($bucket);

                return $this->error('RATE_LIMITED', 'Limite de requisições atingido.', 429)
                    ->withHeaders([
                        'Retry-After' => $retry,
                        'X-RateLimit-Limit' => $max,
                        'X-RateLimit-Remaining' => 0,
                    ]);
            }
        }

        foreach ($buckets as [$bucket, $max]) {
            RateLimiter::hit($bucket, 60);
        }

        return null;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
