<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /** Tela de ativação: o morador define a senha a partir do link do convite. */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/AcceptInvitation', [
            'email' => (string) $request->query('email', ''),
            'token' => (string) $request->route('token'),
        ]);
    }

    /** Valida o token do convite, define a senha, ativa a conta e autentica. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        // Autentica o morador recém-ativado e leva ao portal (global scope de tenant ativo).
        $user = User::where('email', $request->email)->first();
        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('portal.dashboard')->with('success', 'Acesso ativado! Bem-vindo(a) ao portal.');
    }
}
