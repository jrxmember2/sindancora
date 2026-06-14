<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\WhatsAppChannel;

trait RespectsNotificationPreferences
{
    /** @param array<int, string|class-string> $defaultChannels */
    protected function preferredChannels(object $notifiable, string $event, array $defaultChannels): array
    {
        if (! method_exists($notifiable, 'notificationChannelsFor')) {
            return $defaultChannels;
        }

        $defaultKeys = array_map(fn (string $channel) => $this->channelKey($channel), $defaultChannels);
        $enabledKeys = $notifiable->notificationChannelsFor($event, $defaultKeys);

        return array_values(array_filter(
            $defaultChannels,
            fn (string $channel) => in_array($this->channelKey($channel), $enabledKeys, true),
        ));
    }

    private function channelKey(string $channel): string
    {
        return match ($channel) {
            WhatsAppChannel::class => 'whatsapp',
            FcmChannel::class => 'fcm',
            default => $channel,
        };
    }
}
