<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'condominium_id', 'category', 'description', 'amount',
        'expense_date', 'supplier', 'receipt_storage_object_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public const CATEGORIES = [
        'utilities' => 'Concessionárias (água/luz/gás)',
        'maintenance' => 'Manutenção',
        'staff' => 'Pessoal/Folha',
        'cleaning' => 'Limpeza',
        'admin' => 'Administrativo',
        'other' => 'Outro',
    ];

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StorageObject::class, 'receipt_storage_object_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
