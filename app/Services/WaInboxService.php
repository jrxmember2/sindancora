<?php

namespace App\Services;

use App\Exceptions\StorageQuotaException;
use App\Models\Tenant;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappConnection;
use App\Services\StorageService;
use Illuminate\Support\Facades\Log;

/**
 * Ingestão e registro de mensagens da inbox. Resolve o condomínio APENAS quando a conexão
 * atende exatamente 1 condomínio (Fase 2); conexões multi-condomínio ficam sem condomínio até
 * o roteamento por chatbot (Fase 3). Mídia é armazenada via StorageService (Fase 4). Sempre define
 * tenant_id explícito (roda sem tenant no webhook).
 */
class WaInboxService
{
    public function __construct(private readonly StorageService $storage) {}

    /**
     * Registra uma mensagem recebida/enviada vinda do webhook. Deduplica pelo id do WhatsApp.
     * $media (quando presente) = ['type','contents','mime','filename'] — armazenado e vinculado.
     */
    public function ingestMessage(
        WhatsappConnection $connection,
        string $phone,
        ?string $name,
        ?string $waId,
        ?string $body,
        string $direction,
        ?array $media = null,
    ): ?WaConversation {
        if (blank($phone)) {
            return null;
        }

        if ($waId && WaMessage::where('wa_message_id', $waId)->exists()) {
            return null; // já registrada (inclui o eco das que nós mesmos enviamos)
        }

        $condominiumId = $this->resolveCondominium($connection);

        $conversation = WaConversation::firstOrNew([
            'connection_id' => $connection->id,
            'contact_phone' => $phone,
        ]);

        if (! $conversation->exists) {
            $conversation->tenant_id = $connection->tenant_id;
            $conversation->condominium_id = $condominiumId;
            $conversation->contact_name = $name;
            $conversation->status = 'open';
            $conversation->unread_count = 0;
        } else {
            if (! $conversation->condominium_id && $condominiumId) {
                $conversation->condominium_id = $condominiumId;
            }
            if (blank($conversation->contact_name) && filled($name)) {
                $conversation->contact_name = $name;
            }
        }

        $conversation->last_message_at = now();

        if ($direction === 'in') {
            $conversation->unread_count = ($conversation->unread_count ?? 0) + 1;
            if ($conversation->status === 'closed') {
                $conversation->status = 'open'; // reabre ao receber nova mensagem
            }
        }

        $conversation->save();

        // Armazena a mídia (se houver) e vincula à mensagem. Estourar a cota não derruba o webhook.
        $mediaType = null;
        $storageObjectId = null;
        if ($media) {
            $mediaType = $media['type'];
            $storageObjectId = $this->storeMedia($connection, $conversation, $media);
        }

        WaMessage::create([
            'tenant_id' => $connection->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => $direction,
            'body' => $body,
            'media_type' => $mediaType,
            'storage_object_id' => $storageObjectId,
            'wa_message_id' => $waId,
            'created_at' => now(),
        ]);

        // Triagem do chatbot (Fase 3): só para mensagens recebidas e enquanto não roteada.
        // Resolvido pelo container para evitar ciclo de dependência (o bot usa este serviço).
        if ($direction === 'in' && $connection->bot_enabled && $conversation->bot_state !== 'routed') {
            app(WhatsappBotService::class)->handleIncoming($conversation, $body);
        }

        return $conversation;
    }

    /** Registra uma mensagem de saída enviada pela inbox (pelo atendente ou bot). */
    public function recordOutbound(
        WaConversation $conversation,
        ?string $body,
        ?string $waId,
        ?string $userId,
        ?string $mediaType = null,
        ?string $storageObjectId = null,
    ): WaMessage {
        $conversation->update(['last_message_at' => now()]);

        return WaMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'body' => $body,
            'media_type' => $mediaType,
            'storage_object_id' => $storageObjectId,
            'wa_message_id' => $waId,
            'sent_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Armazena a mídia recebida via StorageService; retorna o id do objeto ou null em falha/cota. */
    private function storeMedia(WhatsappConnection $connection, WaConversation $conversation, array $media): ?string
    {
        $tenant = Tenant::find($connection->tenant_id);
        if (! $tenant) {
            return null;
        }

        try {
            $object = $this->storage->storeRaw(
                tenant: $tenant,
                entityType: 'wa_media',
                entityId: $conversation->id,
                contents: $media['contents'],
                filename: $media['filename'],
                mimeType: $media['mime'] ?? null,
                visibility: 'tenant',
                condominiumId: $conversation->condominium_id,
                maxBytes: config('services.evolution.media_max_mb') * 1024 * 1024,
            );

            return $object->id;
        } catch (StorageQuotaException $e) {
            Log::warning('Mídia de WhatsApp não armazenada (cota excedida)', ['tenant' => $tenant->id]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Falha ao armazenar mídia de WhatsApp', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function markRead(WaConversation $conversation): void
    {
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }
    }

    /** Condomínio da conexão quando ela atende exatamente um (senão null → triagem na Fase 3). */
    private function resolveCondominium(WhatsappConnection $connection): ?string
    {
        $ids = $connection->condominiums()->pluck('condominiums.id');

        return $ids->count() === 1 ? $ids->first() : null;
    }
}
