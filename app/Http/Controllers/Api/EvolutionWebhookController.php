<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
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
    public function __construct(private readonly WaInboxService $inbox) {}

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
            $body = $this->extractText($entry['message'] ?? []);

            $this->inbox->ingestMessage(
                connection: $connection,
                phone: $phone,
                name: $name,
                waId: $waId,
                body: $body,
                direction: $fromMe ? 'out' : 'in',
            );
        }
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

        if (filled($text)) {
            return $text;
        }

        // Mensagens de mídia (Fase 4) entram como placeholder por ora.
        return empty($message) ? null : '[mídia não suportada nesta fase]';
    }

    private function normalizeEvent(string $event): string
    {
        return str_replace(['-', '_'], '.', strtolower($event));
    }
}
