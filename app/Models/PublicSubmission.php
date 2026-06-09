<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAttachments;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Envio público pendente de moderação: auto-cadastro de morador ou abertura de ocorrência
 * a partir do link/QR do condomínio. O gestor aprova (gerando Pessoa/vínculo ou Ocorrência)
 * ou reprova. Mantém trilha: quem revisou, quando e o que foi gerado.
 */
class PublicSubmission extends Model
{
    use BelongsToTenant, HasAttachments, HasUuidKey;

    public const ATTACHMENT_ENTITY = 'public_submission';

    protected $table = 'public_submissions';

    public const TYPES = [
        'resident_signup' => 'Auto-cadastro de morador',
        'occurrence' => 'Ocorrência pública',
    ];

    public const STATUSES = [
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'rejected' => 'Reprovado',
    ];

    protected $fillable = [
        'tenant_id', 'condominium_id', 'type', 'status', 'protocol',
        'name', 'email', 'phone', 'document', 'payload',
        'reviewed_by', 'reviewed_at', 'review_notes',
        'person_id', 'occurrence_id', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Código curto de acompanhamento, único dentro do tenant. */
    public static function generateProtocol(string $tenantId): string
    {
        do {
            $protocol = Str::upper(Str::random(8));
        } while (self::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('protocol', $protocol)
            ->exists());

        return $protocol;
    }
}
