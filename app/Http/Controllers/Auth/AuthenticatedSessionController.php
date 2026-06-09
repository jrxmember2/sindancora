<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // "Manter conectado por 12 horas": marcado → cookie persiste por 12h (lifetime da sessão);
        // desmarcado → cookie de sessão (expira ao fechar o navegador). No servidor, o tempo de vida
        // é sempre o lifetime configurado (720 min).
        if (! $request->boolean('remember')) {
            config(['session.expire_on_close' => true]);
        }

        $request->session()->regenerate();

        $user = auth()->user();

        // Roteia por papel: super admin → admin; gestor → painel; porteiro → portaria; morador → portal.
        if ($user->is_super_admin) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect(route($user->homeRoute(), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
