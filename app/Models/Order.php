<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_HANDED_TO_COURIER = 'handed_to_courier';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_SERVED = 'served';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FULFILLMENT_DELIVERY = 'delivery';
    public const FULFILLMENT_PICKUP = 'pickup';
    public const FULFILLMENT_DINE_IN = 'dine_in';

    public const PAYMENT_ONLINE = 'online';
    public const PAYMENT_CASH_ON_RECEIPT = 'cash_on_receipt';
    public const PAYMENT_CARD_ON_RECEIPT = 'card_on_receipt';
    public const PAYMENT_GUEST_ACCOUNT = 'guest_account';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_SUCCEEDED = 'succeeded';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_CANCELLED = 'cancelled';
    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'order_number',
        'idempotency_key',
        'fulfillment_type',
        'status',
        'payment_method',
        'payment_status',
        'customer_name',
        'customer_phone',
        'customer_comment',
        'delivery_address',
        'table_number',
        'guest_card_identifier',
        'requested_at',
        'subtotal',
        'delivery_fee',
        'discount_total',
        'total',
        'currency',
        'confirmed_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'customer_id' => 'integer',
            'delivery_address' => 'array',
            'requested_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'receipt_token_expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderReview::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)
            ->orderBy('created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }
}
