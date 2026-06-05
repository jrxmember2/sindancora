<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensagens configuráveis do chatbot por condomínio (saudação, cabeçalho do menu de setores e
 * texto de opção inválida). Os atributos têm padrões sensíveis quando o síndico não personaliza.
 */
class WhatsappBotSetting extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'whatsapp_bot_settings';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'is_enabled',
        'greeting_message', 'sector_menu_header', 'invalid_option_message',
    ];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public const DEFAULT_GREETING = 'Olá! 👋 Você está falando com o atendimento do condomínio.';
    public const DEFAULT_SECTOR_MENU_HEADER = 'Digite o número do setor que deseja falar:';
    public const DEFAULT_INVALID = 'Opção inválida. Por favor, responda com o número de uma das opções abaixo.';

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function greeting(): string
    {
        return filled($this->greeting_message) ? $this->greeting_message : self::DEFAULT_GREETING;
    }

    public function sectorMenuHeader(): string
    {
        return filled($this->sector_menu_header) ? $this->sector_menu_header : self::DEFAULT_SECTOR_MENU_HEADER;
    }

    public function invalidMessage(): string
    {
        return filled($this->invalid_option_message) ? $this->invalid_option_message : self::DEFAULT_INVALID;
    }
}
