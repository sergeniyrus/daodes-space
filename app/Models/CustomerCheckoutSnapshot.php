<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCheckoutSnapshot extends Model
{
    protected $fillable = [
        'customer_id',
        'organization_id',
        'idempotency_key',
        'payload',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'organization_id' => 'integer',
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
