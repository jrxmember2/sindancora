<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'condominium_id',
        'person_id',
        'created_by',
        'name',
        'document',
        'email',
        'phone',
        'position',
        'employment_type',
        'status',
        'admission_date',
        'termination_date',
        'ctps_number',
        'pis_pasep',
        'salary',
        'vacation_alert_days',
        'notes',
    ];

    protected $appends = ['status_label', 'employment_type_label'];

    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'termination_date' => 'date',
            'salary' => 'decimal:2',
            'vacation_alert_days' => 'integer',
        ];
    }

    public const STATUSES = [
        'active' => 'Ativo',
        'on_vacation' => 'Em ferias',
        'on_leave' => 'Afastado',
        'terminated' => 'Desligado',
    ];

    public const EMPLOYMENT_TYPES = [
        'clt' => 'CLT',
        'contractor' => 'Terceirizado',
        'temporary' => 'Temporario',
        'intern' => 'Estagiario',
        'other' => 'Outro',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vacationPeriods(): HasMany
    {
        return $this->hasMany(EmployeeVacationPeriod::class)->orderByDesc('acquisition_start');
    }

    public function openVacationPeriods(): HasMany
    {
        return $this->hasMany(EmployeeVacationPeriod::class)
            ->whereIn('status', ['pending', 'scheduled'])
            ->orderBy('deadline_date');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getEmploymentTypeLabelAttribute(): string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? $this->employment_type;
    }
}
