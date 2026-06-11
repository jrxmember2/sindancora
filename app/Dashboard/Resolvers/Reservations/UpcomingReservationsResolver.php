<?php

namespace App\Dashboard\Resolvers\Reservations;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

/**
 * Próximas reservas aprovadas/pendentes (a partir de hoje). Timeline.
 */
class UpcomingReservationsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['items' => []]);
        }

        $reservations = $this->reservationBase($ctx)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('date', '>=', today()->toDateString())
            ->with(['commonArea:id,name', 'condominium:id,name'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(6)
            ->get(['id', 'condominium_id', 'common_area_id', 'date', 'start_time', 'status']);

        $items = $reservations->map(fn ($r) => [
            'title' => $r->commonArea?->name ?? 'Área comum',
            'subtitle' => ($r->condominium?->name ?? '').' · '.optional($r->date)->format('d/m')
                .(($r->start_time) ? ' às '.substr((string) $r->start_time, 0, 5) : ''),
            'time' => optional($r->date)->format('d/m'),
            'color' => $r->status === 'approved' ? 'emerald' : 'amber',
            'href' => '/reservas/'.$r->id,
        ])->all();

        if ($items === []) {
            return $this->empty(['items' => [], 'emptyText' => 'Nenhuma reserva agendada.']);
        }

        return ['items' => $items];
    }
}
