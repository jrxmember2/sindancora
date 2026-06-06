<?php

namespace App\Console\Commands;

use App\Models\Occurrence;
use App\Models\User;
use App\Notifications\OccurrenceSlaDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyOccurrenceSla extends Command
{
    protected $signature = 'occurrences:notify-sla';

    protected $description = 'Notifica responsável e gestores sobre chamados próximos do prazo ou com SLA estourado.';

    public function handle(): int
    {
        // Sem contexto de tenant: o global scope BelongsToTenant não filtra (varre todos os tenants).
        $occurrences = Occurrence::query()
            ->dueForSlaAlert()
            ->with('assignee')
            ->get();

        $notified = 0;
        $managersByTenant = [];

        foreach ($occurrences as $occurrence) {
            $managers = $managersByTenant[$occurrence->tenant_id] ??= User::query()
                ->where('tenant_id', $occurrence->tenant_id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', User::PANEL_ROLES))
                ->get();

            // Responsável + gestores, sem duplicar.
            $recipients = $managers->collect();
            if ($occurrence->assignee && $occurrence->assignee->status === 'active') {
                $recipients = $recipients->push($occurrence->assignee)->unique('id');
            }

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new OccurrenceSlaDue($occurrence));
                $notified++;
            }

            $occurrence->forceFill(['sla_notified_at' => now()])->saveQuietly();
        }

        $this->info("{$occurrences->count()} chamado(s) processado(s); {$notified} com avisos enviados.");

        return self::SUCCESS;
    }
}
