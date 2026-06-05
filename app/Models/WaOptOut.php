<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Telefone que optou por não receber disparos em massa (opt-out / LGPD). Por tenant.
 * Preenchido manualmente ou automaticamente quando o contato responde SAIR/PARAR.
 */
class WaOptOut extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'wa_opt_outs';

    public $timestamps = false; // só created_at (default no banco)

    protected $fillable = ['tenant_id', 'phone', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Normaliza um telefone para dígitos com DDI (prefixa 55 quando BR sem DDI). */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if (blank($digits)) {
            return null;
        }

        return strlen($digits) <= 11 ? '55'.$digits : $digits;
    }
}
