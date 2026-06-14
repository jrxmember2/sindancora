<?php

namespace App\Notifications\Channels;

use App\Models\UserDevice;
use App\Services\Push\FcmClient;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificação que envia push (FCM) aos dispositivos registrados do usuário.
 * Lê `toFcm($notifiable)` quando a notificação o define; senão deriva de `toArray()`.
 * Remove tokens mortos (UNREGISTERED) automaticamente.
 */
class FcmChannel
{
    public function __construct(private readonly FcmClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $this->client->isConfigured() || ! method_exists($notifiable, 'getKey')) {
            return;
        }

        $payload = method_exists($notification, 'toFcm')
            ? $notification->toFcm($notifiable)
            : $this->fromArray($notification->toArray($notifiable));

        $data = array_map(
            fn ($v) => (string) $v,
            array_filter([
                'title' => $payload['title'] ?? 'SindÂncora',
                'body' => $payload['body'] ?? '',
                'route' => $payload['route'] ?? null,
                'type' => $payload['type'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
        );

        $devices = UserDevice::withoutGlobalScope('tenant')
            ->where('user_id', $notifiable->getKey())
            ->get();

        foreach ($devices as $device) {
            if ($this->client->send($device->fcm_token, $data) === FcmClient::INVALID) {
                $device->delete();
            }
        }
    }

    /** Fallback: aproveita o payload do canal `database` (title/message/url/icon). */
    private function fromArray(array $arr): array
    {
        return [
            'title' => $arr['title'] ?? 'SindÂncora',
            'body' => $arr['message'] ?? '',
            'route' => $arr['fcm_route'] ?? null,
            'type' => $arr['icon'] ?? null,
        ];
    }
}
