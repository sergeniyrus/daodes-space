<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomerDevice extends Model
{
    protected $table = 'customer_devices';
    protected $fillable = ['customer_id', 'platform', 'device_name', 'push_token', 'token_hash', 'is_active', 'last_seen_at'];
    protected function casts(): array
    {
        return ['customer_id' => 'integer', 'push_token' => 'encrypted', 'is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function pushDeliveries(): HasMany
    {
        return $this->hasMany(PushNotificationDelivery::class);
    }
}
