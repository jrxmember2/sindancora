<?php

namespace App\Console\Commands;

use App\Services\Billing\DunningService;
use Illuminate\Console\Command;

class RunBillingDunning extends Command
{
    protected $signature = 'billing:run-dunning';

    protected $description = 'Régua de cobrança SaaS: lembretes, avisos de atraso, bloqueio em D+15 e desbloqueio por confiança.';

    public function handle(DunningService $dunning): int
    {
        $stats = $dunning->run();

        $this->info(sprintf(
            'Régua executada: %d avaliada(s), %d e-mail(s), %d suspensa(s), %d carência(s) por confiança.',
            $stats['processed'], $stats['emails'], $stats['suspended'], $stats['trust'],
        ));

        return self::SUCCESS;
    }
}
