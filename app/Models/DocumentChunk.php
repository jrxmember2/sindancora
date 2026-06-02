<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasUuidKey;

    public $timestamps = false; // só created_at (useCurrent)

    protected $fillable = [
        'tenant_id', 'document_id', 'condominium_id', 'chunk_index', 'content',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
