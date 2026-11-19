<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'payment_id',
        'event_type',
        'payload_hash',
        'processing_status',
        'processing_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'payment_id');
    }
}
