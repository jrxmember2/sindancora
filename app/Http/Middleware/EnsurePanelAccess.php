<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante acesso ao painel administrativo. Moradores "puros" (apenas role morador,
 * sem papel de gestão) são redirecionados ao portal do morador.
 */
class EnsurePanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canAccessPanel()) {
            // Porteiro → portaria; morador → portal.
            return redirect()->route($user->homeRoute());
        }

        return $next($request);
    }
}
