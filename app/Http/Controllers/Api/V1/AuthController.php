<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = strtolower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        $user = User::where('email', $request->email)
            ->when(app()->bound('tenant_id'), fn ($q) => $q->where('tenant_id', app('tenant_id')))
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas estão incorretas.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'Sua conta está inativa. Entre em contato com o administrador.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('api')->plainTextToken;

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => $user->is_super_admin,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sessão encerrada com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_super_admin' => $user->is_super_admin,
                'tenant' => $user->tenant ? [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'slug' => $user->tenant->slug,
                    'status' => $user->tenant->status,
                    'brand_name' => $user->tenant->getBrandName(),
                    'primary_color' => $user->tenant->getPrimaryColor(),
                ] : null,
            ],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'Se o e-mail informado estiver cadastrado, você receberá as instruções de redefinição.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Token inválido ou expirado.',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Senha redefinida com sucesso.']);
    }
}
