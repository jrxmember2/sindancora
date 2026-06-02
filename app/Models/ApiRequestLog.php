<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    use HasUuidKey;

    public $timestamps = false; // a tabela só tem created_at (useCurrent)

    protected $fillable = [
        'tenant_id', 'api_key_id', 'method', 'path', 'status_code',
        'duration_ms', 'ip_address', 'user_agent', 'request_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
