<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyExpiringDocuments extends Command
{
    protected $signature = 'documents:notify-expiring';

    protected $description = 'Notifica os gestores sobre documentos vencendo dentro da janela de alerta (e já vencidos não notificados).';

    public function handle(): int
    {
        // Sem contexto de tenant: o global scope BelongsToTenant não filtra,
        // varrendo todos os tenants (comportamento desejado para o scheduler).
        $documents = Document::query()
            ->dueForExpiryAlert()
            ->with('condominium:id,name')
            ->get();

        $notified = 0;

        // Cache de usuários-gestores por tenant (evita reconsultar a cada documento).
        $panelUsersByTenant = [];

        foreach ($documents as $document) {
            $users = $panelUsersByTenant[$document->tenant_id] ??= User::query()
                ->where('tenant_id', $document->tenant_id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', User::PANEL_ROLES))
                ->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new DocumentExpiring($document, $document->days_until_expiry));
                $notified++;
            }

            $document->forceFill(['expiry_notified_at' => now()])->saveQuietly();
        }

        $this->info("{$documents->count()} documento(s) processado(s); {$notified} com gestores notificados.");

        return self::SUCCESS;
    }
}
