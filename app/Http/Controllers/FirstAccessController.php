<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login por link mágico do primeiro acesso (enviado no e-mail de boas-vindas após o
 * provisionamento). Valida a assinatura ignorando o domínio — o link é gerado relativo e servido
 * no domínio do tenant.
 */
class FirstAccessController extends Controller
{
    public function login(Request $request, string $user): RedirectResponse
    {
        if (! $request->hasValidSignature(absolute: false)) {
            return redirect('/login')->with('error', 'Link de acesso inválido ou expirado. Use o login com sua senha temporária.');
        }

        $account = User::find($user);
        if (! $account || ! $account->isActive()) {
            return redirect('/login')->with('error', 'Conta indisponível. Entre em contato com o suporte.');
        }

        Auth::login($account, remember: true);
        $request->session()->regenerate();

        return redirect('/perfil')->with('success', 'Bem-vindo! Defina uma senha de sua preferência para concluir o primeiro acesso.');
    }
}
