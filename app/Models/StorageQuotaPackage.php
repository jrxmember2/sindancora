<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class StorageQuotaPackage extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = ['name', 'size_gb', 'price_monthly', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_monthly' => 'decimal:2',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
