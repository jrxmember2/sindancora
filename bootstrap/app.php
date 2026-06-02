<?php

use App\Exceptions\PlanLimitException;
use App\Exceptions\StorageQuotaException;
use App\Http\Middleware\CheckModuleEnabled;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/admin.php',
            __DIR__.'/../routes/portal.php',
        ],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
    at: '*',
    headers: Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO
);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Resolve tenant em todas as requisições (web e API)
        $middleware->prepend(ResolveTenant::class);

        // Aliases de middleware
        $middleware->alias([
            'permission' => CheckPermission::class,
            'module' => CheckModuleEnabled::class,
            'super_admin' => \App\Http\Middleware\IsSuperAdmin::class,
            'panel' => \App\Http\Middleware\EnsurePanelAccess::class,
            'resident' => \App\Http\Middleware\EnsureResident::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Limite de plano → 402
        $exceptions->render(function (PlanLimitException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PLAN_LIMIT_EXCEEDED',
                        'message' => "Limite do plano atingido para '{$e->resource}': {$e->current}/{$e->limit} ({$e->planName}).",
                        'resource' => $e->resource,
                        'current' => $e->current,
                        'limit' => $e->limit,
                        'upgrade_url' => '/configuracoes/plano',
                    ],
                ], 402);
            }
        });

        // Cota de storage → 402
        $exceptions->render(function (StorageQuotaException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'STORAGE_QUOTA_EXCEEDED',
                        'message' => $e->getMessage(),
                        'used_mb' => $e->usedMb,
                        'quota_mb' => $e->quotaMb,
                        'file_size_mb' => $e->fileSizeMb,
                        'upgrade_url' => '/configuracoes/armazenamento',
                    ],
                ], 402);
            }
        });
    })->create();
