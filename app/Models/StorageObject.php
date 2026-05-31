<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageObject extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'entity_type', 'entity_id',
        'storage_provider', 'storage_bucket', 'storage_path',
        'original_filename', 'mime_type', 'file_size_bytes',
        'checksum_sha256', 'visibility', 'uploaded_by',
        'deleted_at', 'permanent_delete_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'deleted_at' => 'datetime',
            'permanent_delete_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeMbAttribute(): float
    {
        return round($this->file_size_bytes / 1024 / 1024, 2);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}
