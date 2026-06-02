<?php

namespace App\Models;

use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class AssemblyVote extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'assembly_id', 'agenda_item_id', 'option_id', 'unit_id', 'person_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
