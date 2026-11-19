<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PushNotificationDelivery extends Model
{
    protected $table = 'push_notification_deliveries';
    protected $fillable = ['push_notification_id', 'customer_device_id', 'status', 'attempts', 'sent_at', 'last_error'];
    protected function casts(): array
    {
        return ['push_notification_id' => 'integer', 'customer_device_id' => 'integer', 'attempts' => 'integer', 'sent_at' => 'datetime'];
    }
    public function notification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class, 'push_notification_id');
    }
    public function device(): BelongsTo
    {
        return $this->belongsTo(CustomerDevice::class, 'customer_device_id');
    }
}
