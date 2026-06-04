<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Config GLOBAL do servidor Evolution API (nível plataforma / super admin). Linha única.
 * NÃO usa escopo de tenant — é a conexão da SindÂncora com o servidor Evolution.
 */
class EvolutionSetting extends Model
{
    use HasUuidKey;

    protected $table = 'evolution_settings';

    protected $fillable = [
        'base_url', 'api_key', 'webhook_url', 'enabled', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    /** Linha única de configuração (cria vazia se não existir). */
    public static function current(): self
    {
        return static::first() ?? static::create(['enabled' => true]);
    }

    public function isUsable(): bool
    {
        return $this->enabled && filled($this->base_url) && filled($this->api_key);
    }
}
