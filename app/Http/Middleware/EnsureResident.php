<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o portal do morador a usuários vinculados a uma Person (moradores).
 * Usuários administrativos sem vínculo de morador são devolvidos ao painel.
 */
class EnsureResident
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Usuário sem vínculo de morador volta para sua área (painel/portaria).
        if ($user && ! $user->person_id && ($user->canAccessPanel() || $user->isGatehouse())) {
            return redirect()->route($user->homeRoute());
        }

        // Sem pessoa vinculada e sem destino próprio: conta sem acesso → 403 (evita loop).
        if (! $user || ! $user->person_id) {
            abort(403, 'Sua conta ainda não tem acesso ao portal do morador.');
        }

        return $next($request);
    }
}
