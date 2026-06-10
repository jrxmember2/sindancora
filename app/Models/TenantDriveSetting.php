<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Integração de armazenamento externo (Google Drive) por tenant. Guarda o refresh_token (encriptado)
 * e a pasta raiz onde a mídia de WhatsApp é gravada. Os arquivos ficam no Drive do tenant e são de
 * responsabilidade dele — não contam na cota do plano.
 */
class TenantDriveSetting extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'tenant_drive_settings';

    protected $fillable = [
        'tenant_id', 'provider', 'account_email', 'refresh_token',
        'root_folder_id', 'status', 'connected_by', 'connected_at', 'last_error',
    ];

    protected $hidden = ['refresh_token'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Conectado e pronto para uso (tem refresh_token e status conectado). */
    public function isActive(): bool
    {
        return $this->status === 'connected' && filled($this->refresh_token);
    }
}
