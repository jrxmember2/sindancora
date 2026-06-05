<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaConversation extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'wa_conversations';

    protected $fillable = [
        'tenant_id', 'connection_id', 'condominium_id', 'sector_id', 'contact_phone', 'contact_name',
        'status', 'bot_state', 'assigned_to', 'unread_count', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
