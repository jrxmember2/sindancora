<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;

class WebhookService
{
    /**
     * Dispara um evento para todos os webhooks ativos do tenant que o assinam.
     * Cada entrega roda em fila (com retry). Seguro de chamar em qualquer contexto
     * (consulta o tenant explicitamente, ignorando o escopo global).
     *
     * @param  array<string,mixed>  $data
     */
    public function dispatch(string $tenantId, string $event, array $data): void
    {
        $webhooks = Webhook::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->get()
            ->filter(fn (Webhook $w) => $w->subscribesTo($event));

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'event' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch($webhook->id, $event, $payload);
        }
    }
}
