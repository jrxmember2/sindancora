<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Notifications\Notification;

class ReservationUpdated extends Notification
{
    public function __construct(
        public Reservation $reservation,
        public string $summary,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Reserva: '.($this->reservation->commonArea?->name ?? 'Área comum'),
            'message' => $this->summary,
            'url' => route('reservations.show', $this->reservation->id),
            'icon' => 'calendar',
        ];
    }
}
