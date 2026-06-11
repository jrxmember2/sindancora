<?php

namespace App\Dashboard\Resolvers\General;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Services\StorageService;

/**
 * Medidor de uso do armazenamento contratado (nível tenant). Usa o cache de uso
 * já existente no StorageService para não disparar o SUM em todo carregamento.
 */
class StorageGaugeResolver extends BaseResolver
{
    public function __construct(private readonly StorageService $storage) {}

    public function resolve(DashboardContext $ctx): array
    {
        $stats = $this->storage->cachedUsageStats($ctx->tenant);
        $pct = (float) $stats['percentage_used'];

        $color = $stats['is_at_limit'] ? 'red' : ($stats['is_near_limit'] ? 'amber' : 'emerald');

        return [
            'label' => 'Armazenamento',
            'value' => min(100, round($pct, 1)),
            'formatted' => sprintf('%.1f GB de %.0f GB', $stats['used_gb'], $stats['quota_gb']),
            'caption' => $stats['is_at_limit']
                ? 'Limite atingido'
                : ($stats['is_near_limit'] ? 'Próximo do limite' : 'Dentro do plano'),
            'color' => $color,
        ];
    }
}
