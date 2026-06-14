<?php

namespace App\Notifications;

use App\Models\Occurrence;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OccurrenceUpdated extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public function __construct(
        public Occurrence $occurrence,
        public string $summary,
    ) {}

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'occurrence_updated', ['database', 'broadcast', WhatsAppChannel::class, FcmChannel::class]);
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "*Ocorrência: {$this->occurrence->title}*\n{$this->summary}";
    }

    /** @return array<string, string> */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Ocorrência: '.$this->occurrence->title,
            'body' => $this->summary,
            'route' => 'occurrences/'.$this->occurrence->id,
            'type' => 'occurrence',
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ocorrência: '.$this->occurrence->title,
            'message' => $this->summary,
            'url' => route('occurrences.show', $this->occurrence->id),
            'icon' => 'alert-circle',
        ];
    }
}
