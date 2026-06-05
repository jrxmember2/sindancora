<?php

namespace App\Services;

use App\Models\Sector;
use App\Models\WaConversation;
use App\Models\WhatsappBotSetting;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use Illuminate\Support\Collection;

/**
 * Chatbot de triagem: ao receber a primeira mensagem de um contato, conduz uma máquina de estados
 *   saudação → (menu de condomínio, se a conexão atende >1) → menu de setor → roteamento.
 * Ao final, define condominium_id + sector_id na conversa (bot_state = routed) e os atendentes
 * daquele setor passam a ver/responder na inbox. Fora do horário do setor, envia a mensagem de
 * ausência. As respostas do bot são gravadas como mensagens de saída (sent_by null = bot).
 *
 * Estados (wa_conversations.bot_state): new | awaiting_condominium | awaiting_sector | routed.
 */
class WhatsappBotService
{
    public function __construct(
        private readonly EvolutionManager $evolution,
        private readonly WaInboxService $inbox,
    ) {}

    /** Processa uma mensagem recebida do contato. Sem efeito quando o bot não se aplica. */
    public function handleIncoming(WaConversation $conversation, ?string $text): void
    {
        $connection = $conversation->connection;

        if (! $connection || ! $connection->bot_enabled || $conversation->bot_state === 'routed') {
            return;
        }

        match ($conversation->bot_state) {
            'awaiting_condominium' => $this->onCondominiumChoice($conversation, $connection, $text),
            'awaiting_sector' => $this->onSectorChoice($conversation, $text),
            default => $this->start($conversation, $connection),
        };
    }

    /** Estado inicial: decide entre menu de condomínio (multi) ou já vai para o menu de setor. */
    private function start(WaConversation $conversation, WhatsappConnection $connection): void
    {
        $condominiums = $connection->condominiums()->orderBy('name')->get(['condominiums.id', 'condominiums.name']);

        if ($condominiums->isEmpty()) {
            $this->route($conversation, null); // nada a triar — vai direto para a inbox geral
            return;
        }

        if ($condominiums->count() > 1) {
            $header = filled($connection->condominium_menu_header)
                ? $connection->condominium_menu_header
                : 'Olá! 👋 Para qual condomínio é o seu atendimento? Responda com o número:';

            $this->send($conversation, $header."\n\n".$this->numberedList($condominiums->pluck('name')));
            $conversation->update(['bot_state' => 'awaiting_condominium']);

            return;
        }

        $this->enterSectorMenu($conversation, $condominiums->first()->id);
    }

    private function onCondominiumChoice(WaConversation $conversation, WhatsappConnection $connection, ?string $text): void
    {
        $condominiums = $connection->condominiums()->orderBy('name')->get(['condominiums.id', 'condominiums.name']);
        $chosen = $this->pick($condominiums, $text);

        if (! $chosen) {
            $this->send($conversation, WhatsappBotSetting::DEFAULT_INVALID."\n\n".$this->numberedList($condominiums->pluck('name')));

            return;
        }

        $conversation->update(['condominium_id' => $chosen->id]);
        $this->enterSectorMenu($conversation, $chosen->id);
    }

    /** Monta e envia o menu de setores do condomínio escolhido (ou roteia direto se não houver). */
    private function enterSectorMenu(WaConversation $conversation, string $condominiumId): void
    {
        $setting = $this->settingFor($condominiumId);
        $conversation->condominium_id ??= $condominiumId;

        if (! $setting->is_enabled) {
            $this->route($conversation, null);
            return;
        }

        $sectors = $this->activeSectors($condominiumId);

        if ($sectors->isEmpty()) {
            $this->route($conversation, null); // sem setores configurados → inbox geral
            return;
        }

        if ($sectors->count() === 1) {
            $this->routeToSector($conversation, $sectors->first());
            return;
        }

        $body = $setting->greeting()."\n\n".$setting->sectorMenuHeader()."\n\n".$this->numberedList($sectors->pluck('name'));
        $this->send($conversation, $body);
        $conversation->update(['bot_state' => 'awaiting_sector', 'condominium_id' => $condominiumId]);
    }

    private function onSectorChoice(WaConversation $conversation, ?string $text): void
    {
        $sectors = $this->activeSectors($conversation->condominium_id);
        $chosen = $this->pick($sectors, $text);

        if (! $chosen) {
            $setting = $this->settingFor($conversation->condominium_id);
            $this->send($conversation, $setting->invalidMessage()."\n\n".$this->numberedList($sectors->pluck('name')));

            return;
        }

        $this->routeToSector($conversation, $chosen);
    }

    /** Conclui o roteamento para um setor: avisa fora de expediente e marca a conversa como roteada. */
    private function routeToSector(WaConversation $conversation, Sector $sector): void
    {
        if (! $sector->isWithinOfficeHours() && filled($sector->away_message)) {
            $this->send($conversation, $sector->away_message);
        } else {
            $this->send($conversation, "Perfeito! Encaminhei seu atendimento para *{$sector->name}*. Em breve responderemos por aqui. 🙂");
        }

        $this->route($conversation, $sector->id);
    }

    private function route(WaConversation $conversation, ?string $sectorId): void
    {
        $conversation->update(['sector_id' => $sectorId, 'bot_state' => 'routed']);
    }

    /** @return Collection<int,Sector> */
    private function activeSectors(?string $condominiumId): Collection
    {
        if (! $condominiumId) {
            return collect();
        }

        return Sector::withoutGlobalScope('tenant')
            ->where('condominium_id', $condominiumId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** Config do bot do condomínio (sempre retorna uma instância, mesmo sem linha persistida). */
    private function settingFor(string $condominiumId): WhatsappBotSetting
    {
        return WhatsappBotSetting::withoutGlobalScope('tenant')
            ->where('condominium_id', $condominiumId)
            ->first()
            ?? new WhatsappBotSetting(['is_enabled' => true]);
    }

    /**
     * Resolve a escolha do usuário (número da opção) numa coleção de itens.
     * Aceita "1", "1.", "opção 1" etc. Índice 1-based.
     */
    private function pick(Collection $items, ?string $text)
    {
        if (blank($text) || $items->isEmpty()) {
            return null;
        }

        if (! preg_match('/\d+/', $text, $m)) {
            return null;
        }

        $index = (int) $m[0] - 1;

        return $items->values()->get($index);
    }

    /** Lista numerada para menus: "1. Portaria\n2. Administração". */
    private function numberedList(Collection $labels): string
    {
        return $labels->values()
            ->map(fn ($label, $i) => ($i + 1).'. '.$label)
            ->implode("\n");
    }

    /** Envia uma mensagem do bot e a registra na thread (sent_by null = bot). */
    private function send(WaConversation $conversation, string $body): void
    {
        $connection = $conversation->connection;
        if (! $connection) {
            return;
        }

        $payload = $this->evolution->sendText($connection, $conversation->contact_phone, $body);
        $waId = $payload['key']['id'] ?? $payload['data']['key']['id'] ?? null;

        $this->inbox->recordOutbound($conversation, $body, $waId, null);
    }
}
