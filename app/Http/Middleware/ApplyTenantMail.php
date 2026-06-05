<?php

namespace App\Http\Middleware;

use App\Services\Mail\TenantMailManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Aplica o SMTP do tenant (resolvido por host) aos envios síncronos da requisição web —
 * recuperação de senha, convite e qualquer e-mail enviado durante o request.
 */
class ApplyTenantMail
{
    public function __construct(private readonly TenantMailManager $mail) {}

    public function handle(Request $request, Closure $next)
    {
        $this->mail->apply(app()->bound('tenant_id') ? app('tenant_id') : null);

        return $next($request);
    }
}
