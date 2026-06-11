<?php

namespace App\Notifications;

use App\Models\Poll;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa o morador que uma nova enquete do seu condomínio está aberta para votação.
 */
class PollOpened extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public ?string $tenantId;

    public function __construct(public Poll $poll)
    {
        $this->tenantId = $poll->tenant_id;
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'poll_opened', ['database', 'broadcast', WhatsAppChannel::class]);
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "*Nova enquete*\nO seu condomínio abriu uma enquete: {$this->poll->title}.\nParticipe pelo portal.";
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nova enquete',
            'message' => $this->poll->title,
            'url' => route('portal.polls.show', $this->poll->id),
            'icon' => 'vote',
        ];
    }
}
