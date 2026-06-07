<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a área da portaria a porteiros e gestores (quem tem gatehouse:register).
 * Demais usuários são devolvidos à sua área de origem.
 */
class EnsureGatehouse
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->hasPermission('gatehouse:register') || $user->isGatehouse())) {
            $plan = app()->bound('tenant') ? app('tenant')->activePlan() : null;

            if ($plan && ! $plan->hasModule('gatehouse')) {
                abort(402, 'O modulo gatehouse nao esta disponivel no seu plano atual.');
            }

            return $next($request);
        }

        if ($user) {
            return redirect()->route($user->homeRoute());
        }

        abort(403);
    }
}
