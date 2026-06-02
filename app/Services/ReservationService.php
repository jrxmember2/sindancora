<?php

namespace App\Services;

use App\Exceptions\ReservationConflictException;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ReservationService
{
    /**
     * Cria uma solicitação de reserva. Se a área não exige aprovação, já aprova
     * (com verificação de conflito). Caso contrário, fica pendente.
     *
     * @param  array{date:string,start_time:string,end_time:string,notes?:string|null}  $data
     */
    public function request(CommonArea $area, array $data, ?string $requesterId): Reservation
    {
        return DB::transaction(function () use ($area, $data, $requesterId) {
            $autoApprove = ! $area->requires_approval;

            if ($autoApprove) {
                $this->assertNoConflict($area->id, $data['date'], $data['start_time'], $data['end_time']);
            }

            $reservation = $area->reservations()->create([
                'tenant_id' => $area->tenant_id,
                'condominium_id' => $area->condominium_id,
                'requested_by' => $requesterId,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'notes' => $data['notes'] ?? null,
                'status' => $autoApprove ? 'approved' : 'pending',
                'decided_at' => $autoApprove ? now() : null,
            ]);

            if ($autoApprove) {
                // Confirmação automática: avisa o solicitante (se não for o próprio ator).
                $this->notifyRequester($reservation, 'Sua reserva foi confirmada automaticamente.');
            } else {
                // Pendente: avisa quem pode aprovar (demais usuários ativos do tenant).
                $this->notifyApprovers($reservation, 'Nova solicitação de reserva aguardando aprovação.');
            }

            return $reservation;
        });
    }

    public function approve(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertNoConflict(
                $reservation->common_area_id,
                $reservation->date->toDateString(),
                $reservation->start_time,
                $reservation->end_time,
                $reservation->id,
            );

            $reservation->forceFill([
                'status' => 'approved',
                'decision_reason' => null,
                'decided_by' => Auth::id(),
                'decided_at' => now(),
            ])->save();

            $this->notifyRequester($reservation, 'Sua reserva foi aprovada.');

            return $reservation;
        });
    }

    public function reject(Reservation $reservation, ?string $reason): Reservation
    {
        $reservation->forceFill([
            'status' => 'rejected',
            'decision_reason' => $reason,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ])->save();

        $this->notifyRequester($reservation, 'Sua reserva foi recusada.'.($reason ? " Motivo: {$reason}" : ''));

        return $reservation;
    }

    public function cancel(Reservation $reservation, ?string $reason): Reservation
    {
        $reservation->forceFill([
            'status' => 'cancelled',
            'decision_reason' => $reason,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ])->save();

        $this->notifyRequester($reservation, 'A reserva foi cancelada.'.($reason ? " Motivo: {$reason}" : ''));

        return $reservation;
    }

    /**
     * Garante que não há reserva aprovada sobrepondo o intervalo solicitado para a área/data.
     * Usa lockForUpdate dentro da transação chamadora para evitar corrida.
     */
    private function assertNoConflict(string $areaId, string $date, string $start, string $end, ?string $excludeId = null): void
    {
        $conflict = Reservation::query()
            ->where('common_area_id', $areaId)
            ->whereDate('date', $date)
            ->where('status', 'approved')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            // Sobreposição: início < fim_existente E fim > início_existente
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new ReservationConflictException;
        }
    }

    private function notifyRequester(Reservation $reservation, string $summary): void
    {
        $requesterId = $reservation->requested_by;
        if (! $requesterId || $requesterId === Auth::id()) {
            return;
        }

        $user = User::where('id', $requesterId)->where('status', 'active')->first();
        if ($user) {
            Notification::send($user, new ReservationUpdated($reservation, $summary));
        }
    }

    private function notifyApprovers(Reservation $reservation, string $summary): void
    {
        $users = User::where('tenant_id', $reservation->tenant_id)
            ->where('status', 'active')
            ->when(Auth::id(), fn ($q) => $q->where('id', '!=', Auth::id()))
            ->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new ReservationUpdated($reservation, $summary));
        }
    }
}
