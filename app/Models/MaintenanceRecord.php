<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Execução registrada de um plano de manutenção (compõe o histórico).
 */
class MaintenanceRecord extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'maintenance_records';

    protected $fillable = [
        'tenant_id', 'maintenance_plan_id', 'supplier_id', 'user_id', 'done_date', 'cost', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'done_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
