<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollOption extends Model
{
    use BelongsToTenant, HasUuidKey;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'poll_id', 'label', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }
}
