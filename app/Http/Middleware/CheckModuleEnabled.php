<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return $next($request);
        }

        $plan = $tenant->activePlan();

        if (! $plan || ! $plan->hasModule($module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'MODULE_NOT_AVAILABLE',
                        'message' => "O módulo '{$module}' não está disponível no seu plano atual.",
                        'upgrade_url' => '/configuracoes/plano',
                    ],
                ], 402);
            }

            abort(402, "O módulo '{$module}' não está disponível no seu plano atual.");
        }

        return $next($request);
    }
}
