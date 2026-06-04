<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessage extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'wa_messages';

    public $timestamps = false; // só created_at (default no banco)

    protected $fillable = [
        'tenant_id', 'conversation_id', 'direction', 'body', 'wa_message_id', 'sent_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
