<?php

namespace App\Notifications;

use App\Models\Occurrence;
use Illuminate\Notifications\Notification;

class OccurrenceUpdated extends Notification
{
    public function __construct(
        public Occurrence $occurrence,
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
            'title' => 'Ocorrência: '.$this->occurrence->title,
            'message' => $this->summary,
            'url' => route('occurrences.show', $this->occurrence->id),
            'icon' => 'alert-circle',
        ];
    }
}
