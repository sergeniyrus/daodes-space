<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotification extends Model
{
    protected $table = 'order_notifications';

    protected $fillable = [
        'organization_id',
        'order_id',
        'recipient_type',
        'recipient_id',
        'event_type',
        'title',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'order_id' => 'integer',
            'recipient_id' => 'integer',
            'read_at' => 'datetime',
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
