<?php
namespace App\Services;
use App\Jobs\SendCustomerPushNotification;
use App\Models\CustomerDevice;
use App\Models\Order;
use App\Models\PushNotification;
use App\Models\PushNotificationDelivery;
class PushNotificationService
{
    public function __construct(private readonly FirebaseMessagingService $messaging)
    {
    }
    public function notifyOrderStatus(Order $order, string $status, string $title, string $body): void
    {
        if (!$order->customer_id || !$this->messaging->isConfigured()) {
            return;
        }
        $notification = PushNotification::query()->firstOrCreate(
            ['event_key' => 'order:' . $order->id . ':status:' . $status],
            [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'event_type' => 'order_status_changed',
                'title' => $title,
                'body' => $body,
                'data' => [
                    'url' => route('customer.orders.show', $order),
                    'order_id' => (string) $order->id,
                    'event_type' => 'order_status_changed',
                ],
                'status' => 'pending',
            ]
        );
        if (!$notification->wasRecentlyCreated) {
            return;
        }
        $devices = CustomerDevice::query()
            ->where('customer_id', $order->customer_id)
            ->where('is_active', true)
            ->get();
        if ($devices->isEmpty()) {
            $notification->update(['status' => 'skipped']);
            return;
        }
        foreach ($devices as $device) {
            $delivery = PushNotificationDelivery::query()->firstOrCreate(
                ['push_notification_id' => $notification->id, 'customer_device_id' => $device->id],
                ['status' => 'pending', 'attempts' => 0]
            );
            if ($delivery->wasRecentlyCreated) {
                SendCustomerPushNotification::dispatch($delivery->id)->afterCommit();
            }
        }
    }
}
