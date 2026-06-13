<?php

namespace App\Jobs;

use App\Models\PaymentEvent;
use App\Services\Billing\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processa um evento do webhook do Asaas (billing SaaS) de forma assíncrona e idempotente.
 * O PaymentEvent já foi persistido (com payload bruto) pelo controller; aqui aplicamos a regra.
 */
class ProcessAsaasBillingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    public function __construct(public string $eventId) {}

    public function handle(BillingService $billing): void
    {
        $event = PaymentEvent::find($this->eventId);

        if (! $event || $event->processed) {
            return; // idempotência: evento ausente ou já processado
        }

        try {
            $payment = $event->payload['payment'] ?? [];
            $billing->handlePaymentEvent($event->event, $payment);

            $event->update(['processed' => true, 'processed_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $event->update(['error' => $e->getMessage()]);
            Log::error('Billing webhook: falha ao processar evento', [
                'event_id' => $event->id, 'event' => $event->event, 'error' => $e->getMessage(),
            ]);
            throw $e; // deixa a fila re-tentar
        }
    }
}
