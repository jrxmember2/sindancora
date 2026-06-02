<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra cada requisição da API pública em api_request_logs (no terminate, para capturar
 * status final e duração). Adiciona X-Request-Id na resposta para rastreio.
 */
class LogApiRequest
{
    private float $startedAt = 0.0;
    private string $requestId = '';

    public function handle(Request $request, Closure $next): Response
    {
        $this->startedAt = microtime(true);
        $this->requestId = (string) Str::uuid();

        $response = $next($request);
        $response->headers->set('X-Request-Id', $this->requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        /** @var ApiKey|null $key */
        $key = app()->bound('api_key') ? app('api_key') : null;
        $tenantId = app()->bound('tenant_id') ? app('tenant_id') : null;

        ApiRequestLog::create([
            'tenant_id' => $tenantId,
            'api_key_id' => $key?->id,
            'method' => $request->method(),
            'path' => Str::limit($request->path(), 500, ''),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $this->startedAt) * 1000),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'request_id' => $this->requestId,
        ]);
    }
}
