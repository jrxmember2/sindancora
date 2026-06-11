<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    private const PLAN_MODULE_BY_PERMISSION_MODULE = [
        'condominiums' => 'condominiums',
        'units' => 'units',
        'persons' => 'persons',
        'announcements' => 'announcements',
        'occurrences' => 'occurrences',
        'reservations' => 'reservations',
        'documents' => 'documents',
        'charges' => 'financial',
        'expenses' => 'financial',
        'reports' => 'reports',
        'assemblies' => 'assemblies',
        'gatehouse' => 'gatehouse',
        'api_keys' => 'api',
        'webhooks' => 'webhooks',
        'ai' => 'ai_assistant',
        'inbox' => 'whatsapp',
        'sectors' => 'whatsapp',
        'campaigns' => 'whatsapp',
        'suppliers' => 'suppliers',
        'employees' => 'employees',
        'maintenance' => 'maintenance',
        'quotations' => 'quotations',
        'works' => 'works',
        'schedule' => 'schedule',
        'public_links' => 'public_links',
        'polls' => 'polls',
        'lost_found' => 'lost_found',
        'disciplinary' => 'disciplinary',
        'community_board' => 'community_board',
    ];

    private const PLAN_MODULE_BY_PERMISSION = [
        'units:import' => 'import',
        'settings:payments' => 'financial',
        'settings:whatsapp' => 'whatsapp',
    ];

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Nao autenticado.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $matchedPermission = null;

        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                continue;
            }

            $matchedPermission ??= $permission;

            if ($this->planAllows($permission)) {
                return $next($request);
            }
        }

        if ($matchedPermission) {
            $module = $this->planModuleForPermission($matchedPermission);
            $plan = app()->bound('tenant') ? app('tenant')->activePlan() : null;
            $planName = $plan?->display_name ?? 'seu plano atual';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'MODULE_NOT_AVAILABLE',
                        'message' => "O modulo '{$module}' nao esta disponivel no plano {$planName}.",
                        'upgrade_url' => '/configuracoes/plano',
                    ],
                ], 402);
            }

            abort(402, "O modulo '{$module}' nao esta disponivel no plano {$planName}.");
        }

        abort(403, 'Voce nao tem permissao para realizar esta acao.');
    }

    private function planAllows(string $permission): bool
    {
        $module = $this->planModuleForPermission($permission);

        if (! $module || ! app()->bound('tenant')) {
            return true;
        }

        $plan = app('tenant')->activePlan();

        return (bool) $plan?->hasModule($module);
    }

    private function planModuleForPermission(string $permission): ?string
    {
        if (isset(self::PLAN_MODULE_BY_PERMISSION[$permission])) {
            return self::PLAN_MODULE_BY_PERMISSION[$permission];
        }

        [$permissionModule] = explode(':', $permission, 2);

        return self::PLAN_MODULE_BY_PERMISSION_MODULE[$permissionModule] ?? null;
    }
}
