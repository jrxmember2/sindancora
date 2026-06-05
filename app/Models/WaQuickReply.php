<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resposta pronta (mensagem canned) usada pelos atendentes na inbox. Por tenant; quando
 * sector_id está preenchido, fica disponível apenas para aquele setor (senão, para todos).
 */
class WaQuickReply extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'wa_quick_replies';

    protected $fillable = [
        'tenant_id', 'sector_id', 'title', 'shortcut', 'body', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
