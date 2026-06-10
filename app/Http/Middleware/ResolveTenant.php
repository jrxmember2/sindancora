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

        // Webhooks de gateways chegam sem sessão e em domínio arbitrário; o tenant é
        // resolvido dentro do controller a partir do payload, não pelo host.
        // O callback do OAuth do Google Drive chega num domínio central fixo (redirect_uri único) e
        // resolve o tenant pelo `state` assinado, não pelo host.
        if ($this->isSuperAdminDomain($host) || $request->is('admin', 'admin/*', 'up', 'api/webhooks/*', 'oauth/google-drive/*')) {
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

        // Cacheia apenas o mapeamento domínio→tenant_id (estável). O modelo Tenant é sempre
        // carregado fresco do banco, para que marca/logo/cor recém-salvas reflitam logo após um
        // refresh — inclusive em deploy com múltiplas réplicas ou cache não compartilhado, onde
        // limpar o cache em um processo não alcança os demais. O lookup por domínio (com JOIN)
        // continua cacheado; só o carregamento do tenant por PK (barato e indexado) é por request.
        $tenantId = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($host) {
            return TenantDomain::where('domain', $host)
                ->where('active', true)
                ->value('tenant_id');
        });

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        // Domínio em cache apontando para tenant inexistente/removido: descarta o mapeamento obsoleto.
        if (! $tenant) {
            Cache::forget($cacheKey);
        }

        return $tenant;
    }
}
