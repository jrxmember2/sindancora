<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAsaasBillingWebhook;
use App\Models\PaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook do billing SaaS (conta Asaas única da plataforma). Distinto de
 * Api\WebhookController@asaas (cobrança tenant → morador). Valida o token, responde 200 rápido e
 * processa em fila. Idempotência garantida por payment_events.asaas_event_id (único).
 */
class AsaasWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expected = config('services.asaas_billing.webhook_token');
        $received = (string) $request->header('asaas-access-token');

        if (blank($expected) || ! hash_equals((string) $expected, $received)) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $event = (string) $request->input('event', '');
        $payment = (array) $request->input('payment', []);

        if ($event === '') {
            // Eventos sem 'event' (ex.: ping) — aceita para não gerar retry.
            return response()->json(['status' => 'ignored']);
        }

        $eventId = $this->idempotencyKey($request, $event, $payment);

        // Persiste o evento bruto; a unicidade de asaas_event_id descarta reprocessamentos.
        $record = PaymentEvent::firstOrCreate(
            ['asaas_event_id' => $eventId],
            [
                'event' => $event,
                'asaas_payment_id' => $payment['id'] ?? null,
                'payload' => $request->all(),
            ],
        );

        if (! $record->wasRecentlyCreated && $record->processed) {
            return response()->json(['status' => 'duplicate']); // já processado
        }

        ProcessAsaasBillingWebhook::dispatch($record->id);

        return response()->json(['status' => 'queued']);
    }

    /** Chave de idempotência: usa o id do evento quando presente; senão deriva do conteúdo. */
    private function idempotencyKey(Request $request, string $event, array $payment): string
    {
        if ($id = $request->input('id')) {
            return (string) $id;
        }

        return $event.'|'.($payment['id'] ?? 'no-id').'|'.($payment['status'] ?? '').'|'.($payment['dateCreated'] ?? '');
    }
}
