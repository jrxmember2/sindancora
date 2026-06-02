<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssemblyAgendaItem extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $fillable = ['tenant_id', 'assembly_id', 'title', 'description', 'position'];

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(Assembly::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssemblyOption::class, 'agenda_item_id')->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(AssemblyVote::class, 'agenda_item_id');
    }
}
