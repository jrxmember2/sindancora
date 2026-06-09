<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Link público (com QR) de um condomínio, usado para auto-cadastro de morador e abertura
 * pública de ocorrência. Há no máximo um link por condomínio (token único). As ações ficam
 * sujeitas a moderação antes de gerar acesso/ocorrência definitivos.
 */
class CondominiumPublicLink extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'condominium_public_links';

    protected $fillable = [
        'tenant_id', 'condominium_id', 'token',
        'active', 'allow_resident_signup', 'allow_occurrence',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'allow_resident_signup' => 'boolean',
            'allow_occurrence' => 'boolean',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    /** Gera um token público curto e único (case-sensitive na URL). */
    public static function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(16));
        } while (self::withoutGlobalScope('tenant')->where('token', $token)->exists());

        return $token;
    }

    /** URL pública absoluta do link (resolvida no domínio do tenant atual). */
    public function publicUrl(): string
    {
        return route('public.intake.landing', ['token' => $this->token]);
    }
}
