<?php

namespace App\Dashboard\Resolvers\Occurrences;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

class OpenOccurrencesResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Ocorrências abertas']);
        }

        $open = $this->occurrenceBase($ctx)->where('status', '!=', 'closed')->count();
        $urgent = $this->occurrenceBase($ctx)
            ->where('status', '!=', 'closed')
            ->whereIn('priority', ['high', 'urgent'])
            ->count();

        return [
            'label' => 'Ocorrências abertas',
            'value' => $open,
            'formatted' => number_format($open, 0, ',', '.'),
            'caption' => $urgent > 0 ? $urgent.' de alta prioridade' : 'Nenhuma urgente',
            'color' => $urgent > 0 ? 'amber' : 'blue',
            'icon' => 'AlertCircle',
        ];
    }
}
