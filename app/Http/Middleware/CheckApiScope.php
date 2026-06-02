<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que a API Key autenticada possui o escopo exigido pela rota.
 * Uso: ->middleware('api.scope:persons:write').
 */
class CheckApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var ApiKey|null $key */
        $key = app()->bound('api_key') ? app('api_key') : null;

        if (! $key || ! $key->hasScope($scope)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_SCOPE',
                    'message' => "Esta API Key não tem o escopo necessário: {$scope}.",
                ],
            ], 403);
        }

        return $next($request);
    }
}
