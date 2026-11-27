<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $table = 'customer_addresses';

    protected $fillable = [
        'customer_id',
        'label',
        'recipient_name',
        'recipient_phone',
        'address_line',
        'house_number',
        'entrance',
        'floor',
        'apartment',
        'intercom',
        'gate_code',
        'delivery_comment',
        'latitude',
        'longitude',
        'is_default',
        'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
            'is_favorite' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
