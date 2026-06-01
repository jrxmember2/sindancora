<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'API REST do SindÂncora — SaaS de Gestão Condominial',
    title: 'SindÂncora API',
    contact: new OA\Contact(email: 'suporte@sindancora.com.br'),
    license: new OA\License(name: 'Proprietário'),
)]
#[OA\Server(url: '/api', description: 'API Base')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Schema(
    schema: 'SuccessResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(
            property: 'error',
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'message', type: 'string'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Tag(name: 'Auth', description: 'Autenticação')]
#[OA\Tag(name: 'Tenant', description: 'Dados do tenant autenticado')]
abstract class ApiController extends Controller {}
