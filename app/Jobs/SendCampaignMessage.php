<?php

namespace App\Jobs;

use App\Models\StorageObject;
use App\Models\WaCampaign;
use App\Models\WaCampaignRecipient;
use App\Models\WaOptOut;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Envia a mensagem de uma campanha a um destinatário. Reconfere opt-out e conexão no momento do
 * envio, atualiza o status do destinatário e os contadores da campanha; ao esgotar os pendentes,
 * marca a campanha como concluída. Roda na fila com throttle dado pelo delay no dispatch.
 */
class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public string $recipientId) {}

    public function handle(EvolutionManager $evolution): void
    {
        $recipient = WaCampaignRecipient::withoutGlobalScope('tenant')->find($this->recipientId);
        if (! $recipient || $recipient->status !== 'pending') {
            return;
        }

        $campaign = WaCampaign::withoutGlobalScope('tenant')->find($recipient->campaign_id);
        if (! $campaign || $campaign->status === 'cancelled') {
            return;
        }

        // Opt-out pode ter sido registrado depois da montagem.
        if (WaOptOut::where('tenant_id', $campaign->tenant_id)->where('phone', $recipient->phone)->exists()) {
            $recipient->update(['status' => 'skipped', 'error' => 'opt-out']);
            $campaign->increment('skipped_count');
            $this->finalize($campaign);

            return;
        }

        $connection = WhatsappConnection::withoutGlobalScope('tenant')->find($campaign->connection_id);
        if (! $connection || $connection->status !== 'connected') {
            $recipient->update(['status' => 'failed', 'error' => 'Conexão indisponível']);
            $campaign->increment('failed_count');
            $this->finalize($campaign);

            return;
        }

        try {
            $payload = $this->send($evolution, $connection, $campaign, $recipient->phone);

            if ($payload === null) {
                throw new \RuntimeException('Envio recusado pela Evolution');
            }

            $waId = $payload['key']['id'] ?? $payload['data']['key']['id'] ?? null;
            $recipient->update(['status' => 'sent', 'wa_message_id' => $waId, 'sent_at' => now()]);
            $campaign->increment('sent_count');
        } catch (\Throwable $e) {
            $recipient->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            $campaign->increment('failed_count');
        }

        $this->finalize($campaign);
    }

    /** Envia texto, ou mídia com legenda quando a campanha tem anexo. */
    private function send(EvolutionManager $evolution, WhatsappConnection $connection, WaCampaign $campaign, string $phone): ?array
    {
        if (! $campaign->media_storage_object_id) {
            return $evolution->sendText($connection, $phone, $campaign->body);
        }

        $object = StorageObject::find($campaign->media_storage_object_id);
        if (! $object) {
            return $evolution->sendText($connection, $phone, $campaign->body);
        }

        $contents = Storage::disk($object->storage_provider)->get($object->storage_path);
        $mime = $object->mime_type ?: 'application/octet-stream';
        $mediatype = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'document');

        return $evolution->sendMedia(
            connection: $connection,
            number: $phone,
            mediatype: $mediatype,
            mimetype: $mime,
            base64: base64_encode((string) $contents),
            fileName: $object->original_filename ?: 'arquivo',
            caption: $campaign->body,
        );
    }

    /** Marca a campanha como concluída quando não há mais destinatários pendentes. */
    private function finalize(WaCampaign $campaign): void
    {
        $campaign->refresh();
        if ($campaign->status === 'sending' && $campaign->recipients()->where('status', 'pending')->doesntExist()) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}
