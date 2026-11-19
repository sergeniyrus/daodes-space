<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReview extends Model
{
    protected $fillable = [
        'organization_id', 'order_id', 'customer_id', 'rating',
        'comment', 'is_public', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'order_id' => 'integer',
            'customer_id' => 'integer',
            'rating' => 'integer',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
