<?php

namespace App\Notifications;

use App\Models\VisitorAuthorization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VisitorArrived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VisitorAuthorization $authorization) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Visitante na portaria',
            'message' => "{$this->authorization->visitor_name} chegou e foi autorizado a entrar.",
            'url' => route('portal.visitors.index'),
            'icon' => 'door-open',
        ];
    }
}
