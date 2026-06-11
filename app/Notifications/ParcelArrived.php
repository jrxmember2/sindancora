<?php

namespace App\Notifications;

use App\Models\Parcel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa o morador da unidade que uma encomenda chegou à portaria e está disponível para retirada.
 */
class ParcelArrived extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    /** Tenant do job (hook de fila aplica o SMTP/tenant correto, quando houver). */
    public ?string $tenantId;

    public function __construct(public Parcel $parcel)
    {
        $this->tenantId = $parcel->tenant_id;
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'parcel_arrived', ['database', 'broadcast', WhatsAppChannel::class]);
    }

    public function toWhatsapp(object $notifiable): string
    {
        $carrier = $this->parcel->carrier ? " ({$this->parcel->carrier})" : '';

        return "*Encomenda na portaria*\nChegou uma encomenda para a sua unidade: {$this->parcel->description}{$carrier}.\nRetire na portaria.";
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Encomenda na portaria',
            'message' => $this->parcel->description.' — disponível para retirada.',
            'url' => route('portal.parcels.index'),
            'icon' => 'package',
        ];
    }
}
