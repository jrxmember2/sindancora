<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLegalDocumentChunk extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = [
        'ai_legal_document_id',
        'chunk_index',
        'content',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiLegalDocument::class, 'ai_legal_document_id');
    }
}
