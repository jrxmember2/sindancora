<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAttachments;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPost extends Model
{
    use BelongsToTenant, HasAttachments, HasUuidKey;

    public const ATTACHMENT_ENTITY = 'community_post';

    public const TYPES = [
        'notice' => 'Mural',
        'classified' => 'Classificado',
    ];

    public const STATUSES = [
        'pending' => 'Pendente',
        'published' => 'Publicado',
        'rejected' => 'Rejeitado',
        'archived' => 'Arquivado',
    ];

    public const CATEGORIES = [
        'sale' => 'Venda',
        'rent' => 'Aluguel',
        'service' => 'Servico',
        'donation' => 'Doacao',
        'notice' => 'Aviso',
        'other' => 'Outro',
    ];

    protected $fillable = [
        'tenant_id',
        'condominium_id',
        'author_user_id',
        'author_person_id',
        'post_type',
        'status',
        'category',
        'title',
        'body',
        'price',
        'contact_name',
        'contact_phone',
        'contact_email',
        'published_at',
        'expires_at',
        'moderated_by',
        'moderated_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'date',
            'moderated_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function authorPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'author_person_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
            });
    }
}
