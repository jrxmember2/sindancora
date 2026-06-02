<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Notifications\ChargeOverdue;
use App\Services\WebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class MarkOverdueCharges extends Command
{
    protected $signature = 'charges:mark-overdue';

    protected $description = 'Marca como vencidas as cobranças pendentes cuja data de vencimento passou e notifica os moradores.';

    public function handle(WebhookService $webhooks): int
    {
        // Sem contexto de tenant: o global scope BelongsToTenant não filtra,
        // varrendo todos os tenants (comportamento desejado para o scheduler).
        $due = Charge::query()
            ->where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->with(['person.user'])
            ->get();

        $notified = 0;

        foreach ($due as $charge) {
            $charge->update(['status' => 'overdue']);

            $webhooks->dispatch($charge->tenant_id, 'charge.overdue', $charge->toWebhookArray());

            $user = $charge->person?->user;
            if ($user && $user->status === 'active') {
                Notification::send($user, new ChargeOverdue($charge));
                $notified++;
            }
        }

        $this->info("{$due->count()} cobrança(s) marcada(s) como vencida(s); {$notified} morador(es) notificado(s).");

        return self::SUCCESS;
    }
}
