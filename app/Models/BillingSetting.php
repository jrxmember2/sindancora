<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração singleton do billing SaaS: régua de cobrança, desbloqueio por confiança e NFS-e.
 */
class BillingSetting extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reminder_days_before', 'overdue_day_1', 'overdue_day_2', 'overdue_day_3', 'suspend_day',
        'trust_unlock_enabled', 'trust_min_months', 'trust_tolerance_days',
        'trust_cooldown_months', 'trust_grace_days',
        'nfse_enabled', 'nfse_service_description', 'nfse_municipal_service_code',
        'nfse_iss_tax', 'nfse_deductions', 'nfse_observations', 'nfse_send_email_to_customer',
    ];

    protected function casts(): array
    {
        return [
            'trust_unlock_enabled' => 'boolean',
            'nfse_enabled' => 'boolean',
            'nfse_send_email_to_customer' => 'boolean',
            'nfse_iss_tax' => 'decimal:2',
            'nfse_deductions' => 'decimal:2',
        ];
    }

    /** A linha única de configuração (cria com defaults se ausente). */
    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }
}
