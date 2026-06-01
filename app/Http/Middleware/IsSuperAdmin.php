<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_super_admin) {
            abort(403, 'Acesso restrito a Super Admins.');
        }

        return $next($request);
    }
}
