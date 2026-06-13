<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Registro de dispositivos do app móvel para push (FCM). O app chama register
 * no login e quando o token FCM rotaciona; unregister no logout/troca de instância.
 */
class DeviceController extends ApiController
{
    #[OA\Post(
        path: '/v1/devices',
        operationId: 'deviceRegister',
        summary: 'Registrar/atualizar dispositivo para notificações push',
        security: [['bearerAuth' => []]],
        tags: ['Dispositivos'],
        responses: [
            new OA\Response(response: 200, description: 'Dispositivo registrado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => 'required|string|max:512',
            'platform' => 'nullable|string|in:android,ios',
            'app_version' => 'nullable|string|max:20',
            'device_name' => 'nullable|string|max:120',
        ]);

        $user = $request->user();

        // Token é único: se já existia (ex.: outro usuário no mesmo aparelho), reaponta.
        $device = UserDevice::withoutGlobalScope('tenant')
            ->updateOrCreate(
                ['fcm_token' => $data['fcm_token']],
                [
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'platform' => $data['platform'] ?? 'android',
                    'app_version' => $data['app_version'] ?? null,
                    'device_name' => $data['device_name'] ?? null,
                    'last_seen_at' => now(),
                ],
            );

        return $this->ok(['id' => $device->id]);
    }

    #[OA\Delete(
        path: '/v1/devices',
        operationId: 'deviceUnregister',
        summary: 'Remover registro do dispositivo (logout/troca de instância)',
        security: [['bearerAuth' => []]],
        tags: ['Dispositivos'],
        responses: [
            new OA\Response(response: 200, description: 'Dispositivo removido'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => 'required|string|max:512',
        ]);

        UserDevice::withoutGlobalScope('tenant')
            ->where('fcm_token', $data['fcm_token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->ok(['removed' => true]);
    }
}
