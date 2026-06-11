<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAttachments;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de achados & perdidos de um condomínio (achado ou perdido), com foto opcional e status.
 */
class LostFoundItem extends Model
{
    use BelongsToTenant, HasAttachments, HasUuidKey;

    public const ATTACHMENT_ENTITY = 'lost_found';

    public const TYPES = [
        'found' => 'Achado',
        'lost' => 'Perdido',
    ];

    public const STATUSES = [
        'open' => 'Em aberto',
        'resolved' => 'Resolvido',
    ];

    protected $fillable = [
        'tenant_id', 'condominium_id', 'type', 'title', 'description',
        'category', 'location', 'status', 'reported_by', 'occurred_on', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
