<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccurrenceComment extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $fillable = [
        'tenant_id', 'occurrence_id', 'user_id', 'type', 'body', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
