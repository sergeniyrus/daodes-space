<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationOrderSetting extends Model
{
    protected $table = 'organization_order_settings';

    protected $fillable = [
        'organization_id',
        'currency',
        'delivery_fee',
        'minimum_order',
        'orders_enabled',
        'delivery_enabled',
        'pickup_enabled',
        'dine_in_enabled',
        'online_payment_enabled',
        'cash_payment_enabled',
        'card_payment_enabled',
        'pickup_min_minutes',
        'delivery_city',
        'pickup_address',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'delivery_fee' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'orders_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
            'dine_in_enabled' => 'boolean',
            'online_payment_enabled' => 'boolean',
            'cash_payment_enabled' => 'boolean',
            'card_payment_enabled' => 'boolean',
            'pickup_min_minutes' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function canAcceptOrders(): bool
    {
        return $this->orders_enabled
            && filled($this->currency)
            && (
                $this->delivery_enabled
                || $this->pickup_enabled
                || $this->dine_in_enabled
            )
            && (
                $this->cash_payment_enabled
                || $this->card_payment_enabled
            );
    }
}
