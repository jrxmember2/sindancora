<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanModule extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = ['plan_id', 'module', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
