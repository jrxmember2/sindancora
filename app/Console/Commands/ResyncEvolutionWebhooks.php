<?php

namespace App\Console\Commands;

use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use Illuminate\Console\Command;

/**
 * Re-registra o webhook (agora com segredo na URL) em todas as instâncias já criadas na Evolution.
 * Rodar após habilitar/alterar o segredo do webhook, senão as instâncias antigas continuam
 * apontando para a URL sem segredo e seus eventos passam a ser rejeitados (403).
 */
class ResyncEvolutionWebhooks extends Command
{
    protected $signature = 'whatsapp:resync-webhooks';

    protected $description = 'Re-registra o webhook (com segredo) em todas as instâncias do Evolution.';

    public function handle(EvolutionManager $evolution): int
    {
        $url = $evolution->registrationWebhookUrl();

        if (blank($url)) {
            $this->error('URL de webhook não configurada no super admin (/admin/whatsapp). Nada a fazer.');

            return self::FAILURE;
        }

        $connections = WhatsappConnection::withoutGlobalScope('tenant')->get(['id', 'instance']);
        $ok = 0;
        $fail = 0;

        foreach ($connections as $connection) {
            if ($evolution->setWebhook($connection->instance, $url)) {
                $ok++;
            } else {
                $fail++;
                $this->warn("Falha ao re-registrar o webhook da instância {$connection->instance}.");
            }
        }

        $this->info("Webhooks re-sincronizados: {$ok} OK, {$fail} falha(s), de {$connections->count()} conexões.");

        return self::SUCCESS;
    }
}
