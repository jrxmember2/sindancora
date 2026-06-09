<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiLegalDocument extends Model
{
    use HasUuidKey, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'category',
        'jurisdiction_level',
        'state',
        'city',
        'storage_provider',
        'storage_bucket',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'is_active',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public const CATEGORIES = [
        'federal_constitution' => 'Constituicao Federal',
        'civil_code' => 'Codigo Civil',
        'penal_code' => 'Codigo Penal',
        'condominium_law' => 'Lei condominial',
        'state_law' => 'Lei estadual',
        'municipal_law' => 'Lei municipal',
        'jurisprudence' => 'Jurisprudencia',
        'platform_guidance' => 'Orientacao da plataforma',
        'reference' => 'Material de referencia',
        'other' => 'Outro',
    ];

    public const JURISDICTIONS = [
        'general' => 'Geral',
        'federal' => 'Federal',
        'state' => 'Estadual',
        'municipal' => 'Municipal',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(AiLegalDocumentChunk::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function jurisdictionLabel(): string
    {
        $jurisdiction = $this->jurisdiction_level ?: 'general';
        $level = self::JURISDICTIONS[$jurisdiction] ?? $jurisdiction;

        if ($jurisdiction === 'state' && $this->state) {
            return "{$level} - {$this->state}";
        }

        if ($jurisdiction === 'municipal') {
            return trim("{$level} - {$this->city}/{$this->state}", ' -/');
        }

        return $level;
    }
}
