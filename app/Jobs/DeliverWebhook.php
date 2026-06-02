<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Entrega um evento a um webhook do tenant. POST assinado (HMAC-SHA256). Cada tentativa é
 * registrada em webhook_deliveries; falhas re-tentam com backoff até $tries.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** Backoff entre tentativas (segundos). */
    public array $backoff = [60, 300, 900];

    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $webhookId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = Webhook::withoutGlobalScope('tenant')->find($this->webhookId);

        if (! $webhook || ! $webhook->active) {
            return; // webhook removido/desativado entre o disparo e a entrega
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, (string) $webhook->secret);
        $startedAt = microtime(true);

        $delivery = new WebhookDelivery([
            'webhook_id' => $webhook->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'attempts' => $this->attempts(),
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-SindAncora-Event' => $this->event,
                'X-SindAncora-Signature' => 'sha256='.$signature,
                'User-Agent' => 'SindAncora-Webhook',
            ])->timeout(15)->withBody($body, 'application/json')->post($webhook->url);

            $delivery->duration_ms = (int) round((microtime(true) - $startedAt) * 1000);
            $delivery->response_status = $response->status();
            $delivery->response_body = mb_substr((string) $response->body(), 0, 2000);

            if ($response->successful()) {
                $delivery->delivered_at = Carbon::now();
                $delivery->save();

                return;
            }

            $delivery->save();
            $this->failOrRetry($delivery);
        } catch (\Throwable $e) {
            $delivery->duration_ms = (int) round((microtime(true) - $startedAt) * 1000);
            $delivery->response_body = mb_substr($e->getMessage(), 0, 2000);
            $delivery->save();
            $this->failOrRetry($delivery);
        }
    }

    /** Marca a falha final ou agenda a re-tentativa. */
    private function failOrRetry(WebhookDelivery $delivery): void
    {
        if ($this->attempts() >= $this->tries) {
            $delivery->failed_at = Carbon::now();
            $delivery->save();

            return;
        }

        $delay = $this->backoff[$this->attempts() - 1] ?? 900;
        $delivery->next_retry_at = Carbon::now()->addSeconds($delay);
        $delivery->save();

        $this->release($delay);
    }
}
