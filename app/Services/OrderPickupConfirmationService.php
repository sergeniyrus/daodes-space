<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Support\OrderNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPickupConfirmationService
{
    public function confirm(Customer $customer, int $orderId): Order
    {
        return DB::transaction(function () use ($customer, $orderId) {
            $order = Order::query()
                ->where('customer_id', $customer->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $order->fulfillment_type !== Order::FULFILLMENT_PICKUP
                || $order->status !== Order::STATUS_READY
            ) {
                throw ValidationException::withMessages([
                    'pickup' => 'Подтвердить получение можно только для готового заказа на самовывоз.',
                ]);
            }

            $order->update(['status' => Order::STATUS_ISSUED]);

            $order->statusHistory()->create([
                'from_status' => Order::STATUS_READY,
                'to_status' => Order::STATUS_ISSUED,
                'actor_type' => 'customer',
                'actor_id' => $customer->id,
                'comment' => 'Клиент самостоятельно подтвердил получение заказа.',
                'created_at' => now(),
            ]);

            app(OrderNotificationService::class)
                ->notifyCustomerAboutStatusChange($order, Order::STATUS_ISSUED);

            return $order->fresh();
        }, 3);
    }
}
