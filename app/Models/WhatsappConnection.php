<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappConnection extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $table = 'whatsapp_connections';

    protected $fillable = [
        'tenant_id', 'name', 'instance', 'token', 'phone_number',
        'status', 'bot_enabled', 'last_connected_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'bot_enabled' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'disconnected' => 'Desconectado',
        'connecting' => 'Conectando',
        'connected' => 'Conectado',
    ];

    public function condominiums(): BelongsToMany
    {
        return $this->belongsToMany(Condominium::class, 'whatsapp_connection_condominium', 'connection_id', 'condominium_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /** Atende mais de um condomínio? (quando sim, o chatbot de seleção é obrigatório). */
    public function servesMultipleCondominiums(): bool
    {
        return $this->condominiums()->count() > 1;
    }
}
