<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoOrderMapping extends Model
{
    protected $table = 'iiko_order_mappings';

    protected $fillable = [
        'organization_id',
        'order_id',
        'iiko_order_id',
        'correlation_id',
        'sync_status',
        'iiko_status',
        'attempts',
        'request_hash',
        'last_error',
        'sent_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'order_id' => 'integer',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
