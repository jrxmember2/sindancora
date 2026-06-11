<?php

namespace App\Dashboard\Resolvers\Shortcuts;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

/**
 * Atalhos rápidos para as ações de criação mais comuns. Cada atalho só aparece
 * se o usuário tem a permissão correspondente (mesma convenção do menu lateral).
 */
class QuickActionsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        $perms = $ctx->user->permissionNames();
        $can = fn (string $p) => in_array('*', $perms, true) || in_array($p, $perms, true);

        $catalog = [
            ['label' => 'Novo comunicado', 'href' => '/comunicados/criar', 'icon' => 'Megaphone', 'color' => 'blue', 'permission' => 'announcements:create'],
            ['label' => 'Nova cobrança', 'href' => '/cobrancas/criar', 'icon' => 'Wallet', 'color' => 'emerald', 'permission' => 'charges:create'],
            ['label' => 'Nova conta a pagar', 'href' => '/despesas/criar', 'icon' => 'Receipt', 'color' => 'red', 'permission' => 'expenses:create'],
            ['label' => 'Nova ocorrência', 'href' => '/ocorrencias/criar', 'icon' => 'AlertCircle', 'color' => 'amber', 'permission' => 'occurrences:create'],
            ['label' => 'Nova unidade', 'href' => '/condominios', 'icon' => 'DoorClosed', 'color' => 'indigo', 'permission' => 'units:create'],
            ['label' => 'Novo documento', 'href' => '/documentos/novo', 'icon' => 'FileText', 'color' => 'sky', 'permission' => 'documents:upload'],
        ];

        $actions = array_values(array_filter(
            $catalog,
            fn (array $a) => $can($a['permission']),
        ));

        // Remove a chave de permissão do payload enviado ao frontend.
        $actions = array_map(fn (array $a) => [
            'label' => $a['label'],
            'href' => $a['href'],
            'icon' => $a['icon'],
            'color' => $a['color'],
        ], $actions);

        if ($actions === []) {
            return $this->empty(['actions' => []]);
        }

        return ['actions' => $actions];
    }
}
