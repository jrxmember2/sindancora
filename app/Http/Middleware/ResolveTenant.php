<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    private const SUPER_ADMIN_HOST = 'admin';
    private const CACHE_TTL = 300; // 5 minutos

    public function handle(Request $request, Closure $next): Response
    {
        $host = $this->extractHost($request);

        if ($this->isSuperAdminDomain($host) || $request->is('admin', 'admin/*', 'up')) {
            return $next($request);
        }

        $tenant = $this->resolveTenantByDomain($host);

        if (! $tenant) {
            abort(404, 'Tenant não encontrado para este domínio.');
        }

        if ($tenant->isSuspended()) {
            abort(402, 'Conta suspensa. Entre em contato com o suporte.');
        }

        if (! $tenant->isActive()) {
            abort(503, 'Conta inativa.');
        }

        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        // Configura o tenant_id na sessão do PostgreSQL para RLS
        try {
            DB::statement("SET app.current_tenant_id = '{$tenant->id}'");
        } catch (\Exception) {
            // Silencia se o banco não suportar (MySQL, SQLite em testes)
        }

        return $next($request);
    }

    private function extractHost(Request $request): string
    {
        $host = $request->getHost();
        // Remove port se existir
        return explode(':', $host)[0];
    }

    private function isSuperAdminDomain(string $host): bool
    {
        $appDomain = config('app.url');
        $subdomain = explode('.', $host)[0] ?? '';
        return $subdomain === self::SUPER_ADMIN_HOST;
    }

    private function resolveTenantByDomain(string $host): ?Tenant
    {
        $cacheKey = "tenant:domain:{$host}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($host) {
            $domain = TenantDomain::with('tenant')
                ->where('domain', $host)
                ->where('active', true)
                ->first();

            return $domain?->tenant;
        });
    }
}
