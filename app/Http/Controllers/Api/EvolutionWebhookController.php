<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use App\Services\WaInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe os webhooks da Evolution API (público, sem tenant por host — resolvido pela instância).
 * Trata mensagens recebidas/enviadas (messages.upsert) e atualização de conexão (connection.update).
 * Sempre responde 200 para evitar reenvio em massa.
 */
class EvolutionWebhookController extends Controller
{
    public function __construct(
        private readonly WaInboxService $inbox,
        private readonly EvolutionManager $evolution,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $event = $this->normalizeEvent((string) $request->input('event'));
            $instance = $request->input('instance');
            $data = $request->input('data', []);

            $connection = WhatsappConnection::withoutGlobalScope('tenant')
                ->where('instance', $instance)
                ->first();

            if (! $connection) {
                return response()->json(['ignored' => true]);
            }

            match ($event) {
                'messages.upsert' => $this->handleMessages($connection, $data),
                'connection.update' => $this->handleConnectionUpdate($connection, $data),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('Evolution webhook falhou', ['error' => $e->getMessage()]);
        }

        return response()->json(['received' => true]);
    }

    private function handleMessages(WhatsappConnection $connection, array $data): void
    {
        $entries = isset($data['key']) ? [$data] : ($data['messages'] ?? []);

        foreach ($entries as $entry) {
            $jid = $entry['key']['remoteJid'] ?? null;
            if (! $jid || str_ends_with($jid, '@g.us') || $jid === 'status@broadcast') {
                continue; // ignora grupos e status
            }

            $phone = preg_replace('/\D/', '', (string) strstr($jid, '@', true) ?: $jid);
            $fromMe = (bool) ($entry['key']['fromMe'] ?? false);
            $waId = $entry['key']['id'] ?? null;
            $name = $entry['pushName'] ?? null;
            $message = $entry['message'] ?? [];

            $media = $this->extractMedia($connection, $entry, $message);
            $body = $media['caption'] ?? $this->extractText($message);

            $this->inbox->ingestMessage(
                connection: $connection,
                phone: $phone,
                name: $name,
                waId: $waId,
                body: $body,
                direction: $fromMe ? 'out' : 'in',
                media: $media['file'] ?? null,
            );
        }
    }

    /**
     * Detecta e baixa a mídia de uma mensagem. Retorna ['caption'=>?string, 'file'=>?array] onde
     * file = ['type','contents','mime','filename']. Prefere o base64 do payload (webhook base64:true)
     * e, em falta, busca na Evolution. Sem mídia → ['caption'=>null,'file'=>null].
     */
    private function extractMedia(WhatsappConnection $connection, array $entry, array $message): array
    {
        // documentWithCaptionMessage embrulha um documentMessage.
        if (isset($message['documentWithCaptionMessage']['message'])) {
            $message = $message['documentWithCaptionMessage']['message'];
        }

        $types = [
            'imageMessage' => 'image',
            'videoMessage' => 'video',
            'audioMessage' => 'audio',
            'documentMessage' => 'document',
            'stickerMessage' => 'sticker',
        ];

        $key = collect($types)->keys()->first(fn ($k) => isset($message[$k]));
        if (! $key) {
            return ['caption' => null, 'file' => null];
        }

        $type = $types[$key];
        $node = $message[$key];
        $caption = $node['caption'] ?? null;

        $base64 = $message['base64'] ?? $entry['base64'] ?? null;
        $mime = $node['mimetype'] ?? null;

        if (blank($base64)) {
            $fetched = $this->evolution->fetchMediaBase64($connection, $entry);
            $base64 = $fetched['base64'] ?? null;
            $mime = $mime ?? ($fetched['mimetype'] ?? null);
        }

        if (blank($base64)) {
            // Sem conteúdo: registra como texto-placeholder, sem arquivo.
            return ['caption' => $caption ?? "[{$type}]", 'file' => null];
        }

        $contents = base64_decode($base64, true);
        if ($contents === false) {
            return ['caption' => $caption ?? "[{$type}]", 'file' => null];
        }

        $filename = $node['fileName'] ?? ($type.'-'.($entry['key']['id'] ?? 'media').'.'.$this->extForMime($mime, $type));

        return [
            'caption' => $caption,
            'file' => ['type' => $type, 'contents' => $contents, 'mime' => $mime, 'filename' => $filename],
        ];
    }

    private function extForMime(?string $mime, string $type): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            'video/mp4' => 'mp4', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'application/pdf' => 'pdf',
        ];

        if ($mime && isset($map[$mime])) {
            return $map[$mime];
        }

        return match ($type) {
            'image' => 'jpg', 'video' => 'mp4', 'audio' => 'ogg', 'sticker' => 'webp', default => 'bin',
        };
    }

    private function handleConnectionUpdate(WhatsappConnection $connection, array $data): void
    {
        $status = match ($data['state'] ?? null) {
            'open' => 'connected',
            'connecting' => 'connecting',
            default => 'disconnected',
        };

        $connection->update([
            'status' => $status,
            'last_connected_at' => $status === 'connected' ? now() : $connection->last_connected_at,
        ]);
    }

    private function extractText(array $message): ?string
    {
        $text = $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? null;

        return filled($text) ? $text : null;
    }

    private function normalizeEvent(string $event): string
    {
        return str_replace(['-', '_'], '.', strtolower($event));
    }
}
