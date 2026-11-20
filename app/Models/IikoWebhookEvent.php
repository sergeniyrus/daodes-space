<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IikoWebhookEvent extends Model
{
    protected $table = 'iiko_webhook_events';

    protected $fillable = [
        'organization_id',
        'iiko_connection_id',
        'iiko_organization_id',
        'terminal_group_id',
        'event_key',
        'event_type',
        'iiko_order_id',
        'event_status',
        'processing_status',
        'received_at',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'iiko_connection_id' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IikoConnection::class, 'iiko_connection_id');
    }
}
