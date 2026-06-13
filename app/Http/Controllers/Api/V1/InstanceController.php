<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Descoberta de instância para o app móvel: o usuário digita o endereço
 * (domínio do tenant), o app valida aqui e obtém branding + versão da API.
 * Público (sem auth); o tenant é resolvido pelo host (ResolveTenant).
 */
class InstanceController extends ApiController
{
    /** Versão do contrato da API consumida pelo app. */
    public const API_VERSION = 1;

    /** Versão mínima do app suportada por este backend (semver). */
    public const MIN_APP_VERSION = '1.0.0';

    #[OA\Get(
        path: '/v1/instance-info',
        operationId: 'instanceInfo',
        summary: 'Descoberta de instância: identidade do sistema, versão da API e branding do tenant',
        tags: ['Instância'],
        responses: [
            new OA\Response(response: 200, description: 'Instância válida', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Tenant não encontrado para este domínio'),
            new OA\Response(response: 402, description: 'Conta suspensa'),
        ],
    )]
    public function show(): JsonResponse
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return $this->ok([
            'system' => 'SindÂncora',
            'version' => config('app.version', '1.0.0'),
            'api_version' => self::API_VERSION,
            'min_app_version' => self::MIN_APP_VERSION,
            'tenant' => $tenant ? [
                'brand_name' => $tenant->getBrandName(),
                'primary_color' => $tenant->getPrimaryColor(),
                'logo_url' => $tenant->getLogoUrl(),
                'status' => $tenant->status,
            ] : null,
        ]);
    }
}
