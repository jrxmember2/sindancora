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
        'status', 'expense_date', 'due_date', 'paid_at', 'paid_amount', 'payment_method',
        'supplier', 'supplier_id', 'document_number', 'reminder_days', 'reminder_sent_at',
        'receipt_storage_object_id', 'maintenance_record_id', 'notes', 'created_by',
    ];

    protected $appends = ['display_status', 'display_status_label', 'days_until_due'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'reminder_days' => 'integer',
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

    public const STATUSES = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'overdue' => 'Vencido',
        'cancelled' => 'Cancelado',
    ];

    public const PAYMENT_METHODS = [
        'bank_transfer' => 'Transferência',
        'pix' => 'PIX',
        'cash' => 'Dinheiro',
        'card' => 'Cartão',
        'boleto' => 'Boleto',
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

    public function supplierRecord(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class, 'maintenance_record_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'pending' && $this->due_date && $this->due_date->lt(today())) {
            return 'overdue';
        }

        return $this->status;
    }

    public function getDisplayStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->display_status] ?? $this->display_status;
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        return (int) round(now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false));
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'overdue']);
    }

    public function scopeDueForReminder($query)
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today()->addDays(60)->toDateString())
            ->whereNull('reminder_sent_at');
    }
}
