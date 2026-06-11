<?php

namespace App\Notifications;

use App\Models\DisciplinaryRecord;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\BroadcastsNotification;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DisciplinaryRecordIssued extends Notification implements ShouldQueue
{
    use BroadcastsNotification, Queueable, RespectsNotificationPreferences;

    public ?string $tenantId;

    public function __construct(public DisciplinaryRecord $record)
    {
        $this->tenantId = $record->tenant_id;
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'disciplinary_record_issued', ['database', 'broadcast', WhatsAppChannel::class]);
    }

    public function toWhatsapp(object $notifiable): string
    {
        $type = DisciplinaryRecord::TYPES[$this->record->type] ?? 'Registro';

        return "*{$type} regimental*\n{$this->record->title}\nConsulte os detalhes no portal.";
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $type = DisciplinaryRecord::TYPES[$this->record->type] ?? 'Registro';

        return [
            'title' => "{$type} regimental",
            'message' => $this->record->title,
            'url' => route('portal.disciplinary.show', $this->record->id),
            'icon' => 'alert-triangle',
        ];
    }
}
