<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuração de e-mail (SMTP + IMAP/Sent) por tenant. As senhas ficam encriptadas.
 */
class TenantMailSetting extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'tenant_mail_settings';

    protected $fillable = [
        'tenant_id', 'enabled',
        'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name',
        'save_to_sent', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'sent_folder',
    ];

    protected $hidden = ['password', 'imap_password'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'save_to_sent' => 'boolean',
            'port' => 'integer',
            'imap_port' => 'integer',
            'password' => 'encrypted',
            'imap_password' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Pronto para enviar por SMTP? */
    public function isUsable(): bool
    {
        return $this->enabled && filled($this->host) && filled($this->username) && filled($this->from_address);
    }

    /** Pronto para salvar cópia no Sent via IMAP? */
    public function imapUsable(): bool
    {
        return $this->save_to_sent && filled($this->imap_host) && filled($this->imap_username) && filled($this->imap_password);
    }
}
