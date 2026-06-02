<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'storage_object_id', 'uploaded_by',
        'title', 'description', 'category', 'visibility',
    ];

    public const CATEGORIES = [
        'minutes' => 'Ata',
        'regulation' => 'Regulamento',
        'contract' => 'Contrato',
        'receipt' => 'Comprovante',
        'other' => 'Outro',
    ];

    public const VISIBILITIES = [
        'residents' => 'Moradores',
        'restricted' => 'Restrito (administração)',
    ];

    /** Mapeia a visibilidade do documento para a visibilidade do StorageObject. */
    public const STORAGE_VISIBILITY = [
        'residents' => 'public_to_residents',
        'restricted' => 'tenant',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function storageObject(): BelongsTo
    {
        return $this->belongsTo(StorageObject::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
