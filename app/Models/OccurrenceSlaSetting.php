<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

/**
 * SLA (prazo em dias por prioridade) configurável por tenant para ocorrências.
 */
class OccurrenceSlaSetting extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'occurrence_sla_settings';

    protected $fillable = ['tenant_id', 'days_by_priority'];

    protected function casts(): array
    {
        return ['days_by_priority' => 'array'];
    }

    /** Dias de SLA para a prioridade (override do tenant ou padrão do Occurrence). */
    public function daysFor(string $priority): int
    {
        $days = $this->days_by_priority[$priority] ?? null;

        return (int) ($days ?? Occurrence::SLA_DEFAULT_DAYS[$priority] ?? 5);
    }
}
