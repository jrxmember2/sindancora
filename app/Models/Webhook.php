<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey;

    protected $fillable = [
        'tenant_id', 'url', 'description', 'events', 'secret', 'active',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'active' => 'boolean',
        ];
    }

    /** Catálogo de eventos disponíveis para assinatura. */
    public const EVENTS = [
        'charge.created' => 'Cobrança criada',
        'charge.paid' => 'Cobrança paga',
        'charge.overdue' => 'Cobrança vencida',
        'announcement.published' => 'Comunicado publicado',
        'occurrence.created' => 'Ocorrência criada',
        'occurrence.status_changed' => 'Ocorrência mudou de status',
        'reservation.created' => 'Reserva solicitada',
        'reservation.approved' => 'Reserva aprovada',
        'reservation.rejected' => 'Reserva recusada',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        return $this->active && in_array($event, $this->events ?? [], true);
    }
}
