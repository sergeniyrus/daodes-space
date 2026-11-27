<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'item_name',
        'item_description',
        'weight',
        'unit',
        'unit_price',
        'quantity',
        'line_total',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'menu_item_id' => 'integer',
            'weight' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
            'options' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
