<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PushNotification extends Model
{
    protected $table = 'push_notifications';
    protected $fillable = ['customer_id', 'order_id', 'event_key', 'event_type', 'title', 'body', 'data', 'status', 'sent_at'];
    protected function casts(): array
    {
        return ['customer_id' => 'integer', 'order_id' => 'integer', 'data' => 'array', 'sent_at' => 'datetime'];
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    public function deliveries(): HasMany
    {
        return $this->hasMany(PushNotificationDelivery::class);
    }
}
