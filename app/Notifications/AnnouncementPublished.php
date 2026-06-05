<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AnnouncementPublished extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable;

    public function __construct(public Announcement $announcement)
    {
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WhatsAppChannel::class];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "*Novo comunicado*\n{$this->announcement->title}";
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Novo comunicado',
            'message' => $this->announcement->title,
            'url' => route('announcements.show', $this->announcement->id),
            'icon' => 'megaphone',
        ];
    }
}
