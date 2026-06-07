<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plano de manutenção preventiva recorrente de um condomínio.
 */
class MaintenancePlan extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $table = 'maintenance_plans';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'supplier_id', 'quotation_proposal_id', 'category', 'title', 'description',
        'frequency', 'next_due_date', 'alert_days', 'last_done_date', 'last_notified_at', 'is_active',
    ];

    protected $appends = ['status', 'days_until_due'];

    protected function casts(): array
    {
        return [
            'next_due_date' => 'date',
            'last_done_date' => 'date',
            'last_notified_at' => 'datetime',
            'alert_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Recorrências disponíveis (slug => rótulo). `once` não recalcula a próxima data. */
    public const FREQUENCIES = [
        'once' => 'Única',
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
        'biennial' => 'Bienal',
    ];

    /** Tipos de manutenção padrão (slug => rótulo). Mesclados com categorias customizáveis tipo 'maintenance'. */
    public const CATEGORIES = [
        'elevador' => 'Elevador',
        'bombas' => 'Bombas',
        'gerador' => 'Gerador',
        'caixa-dagua' => 'Caixa d\'água',
        'dedetizacao' => 'Dedetização',
        'avcb' => 'AVCB/Incêndio',
        'ar-condicionado' => 'Ar-condicionado',
        'portoes' => 'Portões',
        'jardinagem' => 'Jardinagem',
        'outros' => 'Outros',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotationProposal(): BelongsTo
    {
        return $this->belongsTo(QuotationProposal::class, 'quotation_proposal_id');
    }

    /** @return HasMany<MaintenanceRecord> */
    public function records(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class)->latest('done_date');
    }

    /** Dias até a próxima manutenção (negativo = atrasada); null se sem data. */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->next_due_date) {
            return null;
        }

        return (int) round(now()->startOfDay()->diffInDays($this->next_due_date->startOfDay(), false));
    }

    /** Situação: ok | due_soon | overdue | null. */
    public function getStatusAttribute(): ?string
    {
        $days = $this->days_until_due;

        if ($days === null) {
            return null;
        }
        if ($days < 0) {
            return 'overdue';
        }

        return $days <= ($this->alert_days ?? 15) ? 'due_soon' : 'ok';
    }

    /** Próxima data a partir de uma execução, conforme a recorrência. `once` => null. */
    public function nextDateFrom(Carbon $from): ?Carbon
    {
        return match ($this->frequency) {
            'monthly' => $from->copy()->addMonth(),
            'quarterly' => $from->copy()->addMonths(3),
            'semiannual' => $from->copy()->addMonths(6),
            'annual' => $from->copy()->addYear(),
            'biennial' => $from->copy()->addYears(2),
            default => null, // once
        };
    }

    /** Manutenções ativas dentro da janela de alerta ainda não notificadas neste ciclo. */
    public function scopeDueForAlert(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->whereNull('last_notified_at')
            ->whereRaw('next_due_date <= (CURRENT_DATE + COALESCE(alert_days, 15) * INTERVAL \'1 day\')');
    }
}
