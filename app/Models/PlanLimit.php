<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanLimit extends Model
{
    use HasUuidKey;

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['plan_id', 'resource', 'limit_value'];

    protected function casts(): array
    {
        return ['limit_value' => 'integer'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
