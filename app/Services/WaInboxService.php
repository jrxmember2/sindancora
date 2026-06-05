<?php

namespace App\Services;

use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappConnection;

/**
 * Ingestão e registro de mensagens da inbox. Resolve o condomínio APENAS quando a conexão
 * atende exatamente 1 condomínio (Fase 2); conexões multi-condomínio ficam sem condomínio até
 * o roteamento por chatbot (Fase 3). Sempre define tenant_id explícito (roda sem tenant no webhook).
 */
class WaInboxService
{
    /** Registra uma mensagem recebida/enviada vinda do webhook. Deduplica pelo id do WhatsApp. */
    public function ingestMessage(
        WhatsappConnection $connection,
        string $phone,
        ?string $name,
        ?string $waId,
        ?string $body,
        string $direction,
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

        WaMessage::create([
            'tenant_id' => $connection->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => $direction,
            'body' => $body,
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

    /** Registra uma mensagem de saída enviada pela inbox (pelo atendente). */
    public function recordOutbound(WaConversation $conversation, string $body, ?string $waId, ?string $userId): WaMessage
    {
        $conversation->update(['last_message_at' => now()]);

        return WaMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'body' => $body,
            'wa_message_id' => $waId,
            'sent_by' => $userId,
            'created_at' => now(),
        ]);
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
