<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public string $summary,
    ) {
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', WhatsAppChannel::class];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $area = $this->reservation->commonArea?->name ?? 'Área comum';

        return "*Reserva: {$area}*\n{$this->summary}";
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
