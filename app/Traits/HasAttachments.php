<?php

namespace App\Traits;

use App\Models\StorageObject;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Permite anexar múltiplos arquivos (StorageObject) a um model, reusando a camada de
 * storage existente via os campos polimórficos entity_type/entity_id. O model deve
 * definir a constante ATTACHMENT_ENTITY com o entity_type (ex.: 'announcement').
 */
trait HasAttachments
{
    public function attachments(): HasMany
    {
        return $this->hasMany(StorageObject::class, 'entity_id')
            ->where('entity_type', static::ATTACHMENT_ENTITY)
            ->whereNull('deleted_at')
            ->orderBy('created_at');
    }

    /** Serializa os anexos para o frontend (id, nome, tamanho, se é imagem). */
    public function attachmentsPayload(): array
    {
        return $this->attachments->map(fn (StorageObject $o) => [
            'id' => $o->id,
            'name' => $o->original_filename,
            'size_mb' => round($o->file_size_bytes / 1024 / 1024, 2),
            'mime' => $o->mime_type,
            'is_image' => str_starts_with((string) $o->mime_type, 'image/'),
        ])->all();
    }
}
