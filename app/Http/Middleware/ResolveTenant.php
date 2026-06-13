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
        // O site público (planos/checkout/cadastro) roda no domínio apex, sem tenant.
        if ($this->isSuperAdminDomain($host)
            || $request->is('admin', 'admin/*', 'up', 'api/webhooks/*', 'oauth/google-drive/*')
            || $request->is('planos', 'planos/*', 'checkout', 'checkout/*', 'cadastro')) {
            return $next($request);
        }

        $tenant = $this->resolveTenantByDomain($host);

        if (! $tenant) {
            return $this->deny($request, 404, 'TENANT_NOT_FOUND', 'Tenant não encontrado para este domínio.');
        }

        if ($tenant->isSuspended()) {
            // Contrato do app móvel/API: 402 + code TENANT_SUSPENDED dispara a tela "Assinatura em atraso".
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->deny($request, 402, 'TENANT_SUSPENDED', 'Conta suspensa por inadimplência.');
            }

            // Web: a tela de bloqueio (e o logout) precisam ser acessíveis mesmo suspenso. O tenant
            // é resolvido para a tela mostrar a fatura; o resto da aplicação redireciona para lá.
            $this->bindTenant($tenant);

            if ($request->is('assinatura-em-atraso', 'logout')) {
                return $next($request);
            }

            return redirect('/assinatura-em-atraso');
        }

        if (! $tenant->isActive()) {
            return $this->deny($request, 503, 'TENANT_INACTIVE', 'Conta inativa.');
        }

        $this->bindTenant($tenant);

        return $next($request);
    }

    /** Vincula o tenant ao container e configura o RLS do PostgreSQL. */
    private function bindTenant(Tenant $tenant): void
    {
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        try {
            DB::statement("SET app.current_tenant_id = '{$tenant->id}'");
        } catch (\Exception) {
            // Silencia se o banco não suportar (MySQL, SQLite em testes)
        }
    }

    /**
     * Nega o acesso: JSON estruturado para API/app (envelope {success, error{code}})
     * e abort tradicional (página de erro) para web.
     */
    private function deny(Request $request, int $status, string $code, string $message): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => $code, 'message' => $message],
            ], $status);
        }

        abort($status, $message);
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
