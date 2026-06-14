<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationUpdated extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public function __construct(
        public Reservation $reservation,
        public string $summary,
    ) {}

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'reservation_updated', ['database', 'broadcast', WhatsAppChannel::class, FcmChannel::class]);
    }

    public function toWhatsapp(object $notifiable): string
    {
        $area = $this->reservation->commonArea?->name ?? 'Área comum';

        return "*Reserva: {$area}*\n{$this->summary}";
    }

    /** @return array<string, string> */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Reserva: '.($this->reservation->commonArea?->name ?? 'Área comum'),
            'body' => $this->summary,
            'route' => 'reservations/'.$this->reservation->id,
            'type' => 'reservation',
        ];
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
