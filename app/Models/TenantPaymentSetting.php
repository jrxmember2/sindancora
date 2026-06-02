<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPaymentSetting extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'tenant_id', 'provider', 'environment', 'api_key',
        'wallet_id', 'webhook_token', 'billing_type', 'enabled',
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

    /** True quando há chave configurada e a integração está ligada. */
    public function isUsable(): bool
    {
        return $this->enabled && filled($this->api_key);
    }

    /** URL base da API do Asaas conforme o ambiente. */
    public function baseUrl(): string
    {
        $key = $this->environment === 'production' ? 'production' : 'sandbox';

        return config("services.{$this->provider}.{$key}");
    }
}
