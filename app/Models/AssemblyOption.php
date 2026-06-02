<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssemblyOption extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = ['agenda_item_id', 'label', 'position'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssemblyAgendaItem::class, 'agenda_item_id');
    }
}
