<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaCampaignRecipient extends Model
{
    use BelongsToTenant, HasUuidKey;

    protected $table = 'wa_campaign_recipients';

    public $timestamps = false; // só created_at (default no banco)

    protected $fillable = [
        'tenant_id', 'campaign_id', 'person_id', 'name', 'phone',
        'status', 'wa_message_id', 'error', 'sent_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaCampaign::class, 'campaign_id');
    }
}
