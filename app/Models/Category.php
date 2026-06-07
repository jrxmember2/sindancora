<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use BelongsToTenant, HasAuditLog, HasUuidKey, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'type', 'name', 'slug', 'color', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Tipos de entidade que aceitam categorias customizáveis. */
    public const TYPES = [
        'occurrence' => 'Ocorrências',
        'document' => 'Documentos',
        'supplier' => 'Fornecedores',
        'maintenance' => 'Manutenções',
        'quotation' => 'Orçamentos/Cotações',
    ];

    /**
     * Mescla as categorias padrão (constantes) com as customizadas e ativas do tenant.
     * Retorna mapa slug => rótulo, pronto para os selects do front. O valor armazenado
     * continua sendo o slug (string), compatível com os dados existentes.
     *
     * @param  array<string, string>  $base  categorias padrão (slug => rótulo)
     * @return array<string, string>
     */
    public static function optionsFor(string $tenantId, string $type, array $base): array
    {
        $custom = static::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();

        return $base + $custom;
    }

    /** Gera um slug único por tenant+tipo a partir do nome. */
    public static function makeSlug(string $tenantId, string $type, string $name): string
    {
        $base = Str::slug($name) ?: 'cat';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
