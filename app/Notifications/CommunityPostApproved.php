<?php

namespace App\Notifications;

use App\Models\CommunityPost;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CommunityPostApproved extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public ?string $tenantId;

    public function __construct(public CommunityPost $post)
    {
        $this->tenantId = $post->tenant_id;
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'community_post_approved', ['database', 'broadcast']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Classificado publicado',
            'message' => $this->post->title,
            'url' => route('portal.community-board.index'),
            'icon' => 'newspaper',
        ];
    }
}
