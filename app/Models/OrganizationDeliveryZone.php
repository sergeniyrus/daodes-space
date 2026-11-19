<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDeliveryZone extends Model
{
    protected $table = 'organization_delivery_zones';

    protected $fillable = [
        'organization_id',
        'max_distance_km',
        'minimum_order_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'max_distance_km' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
