<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Setor de atendimento por condomínio (Portaria, ADM, etc.). É o destino do roteamento do
 * chatbot e define o escopo de quais conversas cada atendente (membro) vê na inbox.
 */
class Sector extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey;

    protected $table = 'sectors';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'name', 'is_active', 'office_hours', 'away_message', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'office_hours' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** Dias da semana usados na configuração de horário (chave => rótulo). */
    public const WEEKDAYS = [
        'mon' => 'Segunda', 'tue' => 'Terça', 'wed' => 'Quarta', 'thu' => 'Quinta',
        'fri' => 'Sexta', 'sat' => 'Sábado', 'sun' => 'Domingo',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sector_user');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WaConversation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Está dentro do horário de atendimento agora? Sem horários configurados → sempre disponível.
     * Estrutura esperada: ['mon' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'], ...]
     */
    public function isWithinOfficeHours(?Carbon $now = null): bool
    {
        $hours = $this->office_hours;
        if (blank($hours)) {
            return true;
        }

        $now ??= now();
        $key = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$now->dayOfWeekIso - 1];
        $day = $hours[strtolower($key)] ?? null;

        if (! $day || empty($day['enabled']) || blank($day['open'] ?? null) || blank($day['close'] ?? null)) {
            return false;
        }

        $current = $now->format('H:i');

        return $current >= $day['open'] && $current <= $day['close'];
    }
}
