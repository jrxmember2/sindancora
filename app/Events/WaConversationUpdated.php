<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando uma conversa de WhatsApp recebe ou envia mensagem. Broadcast no canal privado
 * do tenant para que a inbox aberta atualize em tempo real (Fase 5). Carrega apenas ids — o
 * frontend recarrega os dados do servidor (fonte da verdade).
 */
class WaConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $conversationId,
        public ?string $sectorId = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}.inbox")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'sector_id' => $this->sectorId,
        ];
    }
}
