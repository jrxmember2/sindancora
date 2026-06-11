<?php

namespace App\Dashboard\Resolvers\Reservations;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

class PendingReservationsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Reservas pendentes']);
        }

        $pending = $this->reservationBase($ctx)->where('status', 'pending')->count();

        return [
            'label' => 'Reservas pendentes',
            'value' => $pending,
            'formatted' => number_format($pending, 0, ',', '.'),
            'caption' => $pending > 0 ? 'Aguardando aprovação' : 'Tudo em dia',
            'color' => $pending > 0 ? 'amber' : 'emerald',
            'icon' => 'CalendarRange',
        ];
    }
}
