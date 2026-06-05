<?php

namespace App\Console\Commands;

use App\Models\WaCampaign;
use App\Services\WaCampaignService;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-scheduled';

    protected $description = 'Inicia o disparo das campanhas de WhatsApp agendadas cuja data já chegou.';

    public function handle(WaCampaignService $service): int
    {
        // Sem contexto de tenant: o global scope BelongsToTenant não filtra (varre todos os tenants).
        $due = WaCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $campaign) {
            $service->start($campaign);
            $this->info("Disparo iniciado: {$campaign->name} ({$campaign->id})");
        }

        $this->info("{$due->count()} campanha(s) agendada(s) iniciada(s).");

        return self::SUCCESS;
    }
}
