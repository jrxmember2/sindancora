<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantWhatsappSetting extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'tenant_id', 'base_url', 'instance', 'api_key', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Integração utilizável: ligada e com URL/instância/chave configuradas. */
    public function isUsable(): bool
    {
        return $this->enabled && filled($this->base_url) && filled($this->instance) && filled($this->api_key);
    }

    /** Endpoint de envio de texto da Evolution API. */
    public function sendTextUrl(): string
    {
        return rtrim((string) $this->base_url, '/').'/message/sendText/'.$this->instance;
    }

    /** Endpoint de estado da conexão (usado pelo "testar"). */
    public function connectionStateUrl(): string
    {
        return rtrim((string) $this->base_url, '/').'/instance/connectionState/'.$this->instance;
    }
}
