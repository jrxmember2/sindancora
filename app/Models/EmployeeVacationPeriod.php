<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeVacationPeriod extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'acquisition_start',
        'acquisition_end',
        'deadline_date',
        'vacation_start',
        'vacation_end',
        'days',
        'status',
        'notified_at',
        'notes',
    ];

    protected $appends = ['status_label', 'days_until_deadline', 'deadline_status'];

    protected function casts(): array
    {
        return [
            'acquisition_start' => 'date',
            'acquisition_end' => 'date',
            'deadline_date' => 'date',
            'vacation_start' => 'date',
            'vacation_end' => 'date',
            'notified_at' => 'datetime',
            'days' => 'integer',
        ];
    }

    public const STATUSES = [
        'pending' => 'Pendente',
        'scheduled' => 'Programada',
        'taken' => 'Gozada',
        'paid_out' => 'Indenizada',
        'cancelled' => 'Cancelada',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getDaysUntilDeadlineAttribute(): ?int
    {
        if (! $this->deadline_date) {
            return null;
        }

        return (int) round(now()->startOfDay()->diffInDays($this->deadline_date->startOfDay(), false));
    }

    public function getDeadlineStatusAttribute(): ?string
    {
        if (in_array($this->status, ['taken', 'paid_out', 'cancelled'], true)) {
            return null;
        }

        $days = $this->days_until_deadline;
        if ($days === null) {
            return null;
        }
        if ($days < 0) {
            return 'overdue';
        }

        $alertDays = $this->employee?->vacation_alert_days ?? 60;

        return $days <= $alertDays ? 'due_soon' : 'ok';
    }

    public function scopeDueForAlert(Builder $query): Builder
    {
        return $query
            ->whereIn('employee_vacation_periods.status', ['pending', 'scheduled'])
            ->whereNotNull('employee_vacation_periods.deadline_date')
            ->whereNull('employee_vacation_periods.notified_at')
            ->whereHas('employee', function (Builder $employee) {
                $employee->whereIn('status', ['active', 'on_vacation', 'on_leave'])
                    ->whereRaw('employee_vacation_periods.deadline_date <= (CURRENT_DATE + COALESCE(employees.vacation_alert_days, 60) * INTERVAL \'1 day\')');
            });
    }
}
