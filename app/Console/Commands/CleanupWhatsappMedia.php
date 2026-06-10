<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\StorageService;
use App\Services\Whatsapp\WhatsappMediaCleanupService;
use Illuminate\Console\Command;

/**
 * Limpeza automática de mídia de WhatsApp por tenant, conforme a política do gestor
 * (tenants.settings.whatsapp_media_cleanup): por data (apaga mais antigas que N dias) ou por cota
 * (apaga ao atingir 85% do armazenamento). Só mexe na mídia da plataforma — a do Drive fica intacta.
 */
class CleanupWhatsappMedia extends Command
{
    protected $signature = 'whatsapp:cleanup-media';

    protected $description = 'Limpa mídia antiga de WhatsApp conforme a política de cada tenant (data ou cota).';

    public function handle(WhatsappMediaCleanupService $cleanup, StorageService $storage): int
    {
        $total = 0;

        foreach (Tenant::active()->cursor() as $tenant) {
            $policy = $tenant->whatsappCleanupPolicy();

            $freed = match ($policy['mode']) {
                'date' => $policy['retention_days'] ? $cleanup->purgeOlderThan($tenant, $policy['retention_days']) : 0,
                'quota' => $storage->getUsageStats($tenant)['is_near_limit'] ? $cleanup->purgeToTarget($tenant) : 0,
                default => 0,
            };

            if ($freed > 0) {
                $this->info("Tenant {$tenant->id} ({$policy['mode']}): {$freed} MB liberados.");
                $total += $freed;
            }
        }

        $this->info("Limpeza concluída. Total liberado: {$total} MB.");

        return self::SUCCESS;
    }
}
