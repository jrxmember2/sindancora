<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avaliação de um fornecedor (nota 1–5 + comentário), compõe o histórico/rating.
 */
class SupplierEvaluation extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'supplier_evaluations';

    protected $fillable = ['tenant_id', 'supplier_id', 'user_id', 'score', 'comment'];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    /** @return BelongsTo<Supplier, SupplierEvaluation> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, SupplierEvaluation> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
