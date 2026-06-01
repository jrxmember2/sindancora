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
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/v1/auth/login',
        operationId: 'authLogin',
        summary: 'Autenticar usuário e obter token Bearer',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@empresa.com.br'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'senha123'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login bem-sucedido', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Credenciais inválidas ou conta inativa'),
            new OA\Response(response: 429, description: 'Muitas tentativas — aguarde antes de tentar novamente'),
        ],
    )]
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

    #[OA\Post(
        path: '/v1/auth/logout',
        operationId: 'authLogout',
        summary: 'Encerrar sessão e revogar token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Sessão encerrada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sessão encerrada com sucesso.']);
    }

    #[OA\Get(
        path: '/v1/me',
        operationId: 'authMe',
        summary: 'Dados do usuário autenticado e seu tenant',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Dados do usuário', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
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

    #[OA\Post(
        path: '/v1/auth/forgot-password',
        operationId: 'authForgotPassword',
        summary: 'Solicitar e-mail de redefinição de senha',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [new OA\Property(property: 'email', type: 'string', format: 'email')],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'E-mail enviado (se o endereço existir)'),
        ],
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'Se o e-mail informado estiver cadastrado, você receberá as instruções de redefinição.',
        ]);
    }

    #[OA\Post(
        path: '/v1/auth/reset-password',
        operationId: 'authResetPassword',
        summary: 'Redefinir senha com token recebido por e-mail',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Senha redefinida com sucesso'),
            new OA\Response(response: 422, description: 'Token inválido ou expirado'),
        ],
    )]
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
