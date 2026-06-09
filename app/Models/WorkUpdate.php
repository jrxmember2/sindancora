<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkUpdate extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey;

    protected $fillable = [
        'tenant_id', 'work_id', 'user_id', 'title', 'description',
        'status', 'progress_percent', 'occurred_at',
    ];

    protected $appends = ['status_label'];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getStatusLabelAttribute(): ?string
    {
        return $this->status ? (Work::STATUSES[$this->status] ?? $this->status) : null;
    }
}
