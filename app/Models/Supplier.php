<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fornecedor/prestador de serviço do tenant (compartilhado entre condomínios).
 */
class Supplier extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $table = 'suppliers';

    protected $fillable = [
        'tenant_id', 'category', 'name', 'document', 'contact_name', 'phone', 'email', 'website',
        'zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Ramos de serviço padrão (slug => rótulo). Mesclados com as categorias customizáveis tipo 'supplier'. */
    public const CATEGORIES = [
        'limpeza' => 'Limpeza',
        'eletrica' => 'Elétrica',
        'hidraulica' => 'Hidráulica',
        'elevador' => 'Elevador',
        'jardinagem' => 'Jardinagem',
        'seguranca' => 'Segurança',
        'dedetizacao' => 'Dedetização',
        'pintura' => 'Pintura',
        'manutencao-geral' => 'Manutenção geral',
        'outros' => 'Outros',
    ];

    /** @return BelongsToMany<Condominium> */
    public function condominiums(): BelongsToMany
    {
        return $this->belongsToMany(Condominium::class, 'supplier_condominium');
    }

    /** @return HasMany<SupplierEvaluation> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class)->latest();
    }
}
