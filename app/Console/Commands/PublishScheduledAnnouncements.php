<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Console\Command;

class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:publish-scheduled';

    protected $description = 'Publica comunicados agendados cuja data de publicação já chegou e enfileira os e-mails.';

    public function handle(AnnouncementService $service): int
    {
        // Sem contexto de tenant: o global scope BelongsToTenant não filtra,
        // varrendo todos os tenants (comportamento desejado para o scheduler).
        $due = Announcement::query()
            ->where('status', 'draft')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($due as $announcement) {
            $service->publish($announcement);
            $this->info("Publicado: {$announcement->title} ({$announcement->id})");
        }

        $this->info("{$due->count()} comunicado(s) agendado(s) publicado(s).");

        return self::SUCCESS;
    }
}
