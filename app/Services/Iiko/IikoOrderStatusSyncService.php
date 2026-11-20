<?php

namespace App\Services\Iiko;

use App\Models\IikoOrderMapping;
use App\Models\Order;
use App\Support\OrderNotificationService;
use Illuminate\Support\Facades\DB;

class IikoOrderStatusSyncService
{
    public function synchronize(IikoOrderMapping $mapping, ?string $iikoStatus): ?string
    {
        $status = mb_strtolower(trim((string) $iikoStatus));

        $order = $mapping->order;

        if (!$order || $status === '') {
            return null;
        }

        $target = match ($status) {
            'waitcooking', 'waiting' => Order::STATUS_CONFIRMED,
            'cookingstarted', 'cooking' => Order::STATUS_PREPARING,
            'cookingcompleted', 'readyforcourier', 'readyforpickup' => Order::STATUS_READY,
            'onway', 'on_the_way' => Order::STATUS_IN_TRANSIT,
            'delivered' => $order->fulfillment_type === Order::FULFILLMENT_PICKUP
                ? Order::STATUS_ISSUED
                : Order::STATUS_DELIVERED,
            'closed' => $order->payment_status === Order::PAYMENT_SUCCEEDED
                ? Order::STATUS_COMPLETED
                : null,
            'cancelled', 'canceled' => $order->payment_status !== Order::PAYMENT_SUCCEEDED
                ? Order::STATUS_CANCELLED
                : null,
            default => null,
        };

        if (!$target) {
            return null;
        }

        $ranks = [
            Order::STATUS_NEW => 0,
            Order::STATUS_AWAITING_PAYMENT => 0,
            Order::STATUS_CONFIRMED => 1,
            Order::STATUS_PREPARING => 2,
            Order::STATUS_READY => 3,
            Order::STATUS_HANDED_TO_COURIER => 4,
            Order::STATUS_IN_TRANSIT => 5,
            Order::STATUS_DELIVERED => 6,
            Order::STATUS_ISSUED => 6,
            Order::STATUS_SERVED => 6,
            Order::STATUS_COMPLETED => 7,
            Order::STATUS_CANCELLED => 8,
        ];

        return DB::transaction(function () use ($order, $target, $ranks, $iikoStatus) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (
                !$lockedOrder ||
                in_array($lockedOrder->status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)
            ) {
                return null;
            }

            if (
                $target === Order::STATUS_COMPLETED &&
                $lockedOrder->payment_status !== Order::PAYMENT_SUCCEEDED
            ) {
                return null;
            }

            if (
                $target === Order::STATUS_CANCELLED &&
                $lockedOrder->payment_status === Order::PAYMENT_SUCCEEDED
            ) {
                return null;
            }

            $from = $lockedOrder->status;

            if (($ranks[$target] ?? -1) <= ($ranks[$from] ?? -1)) {
                return null;
            }

            $updates = ['status' => $target];

            if ($target === Order::STATUS_CONFIRMED) {
                $updates['confirmed_at'] = $lockedOrder->confirmed_at ?? now();
            } elseif ($target === Order::STATUS_READY) {
                $updates['ready_at'] = $lockedOrder->ready_at ?? now();
            } elseif ($target === Order::STATUS_COMPLETED) {
                $updates['completed_at'] = now();
            } elseif ($target === Order::STATUS_CANCELLED) {
                $updates['cancelled_at'] = now();
                $updates['payment_status'] = Order::PAYMENT_CANCELLED;

                $lockedOrder->payments()
                    ->where('status', Order::PAYMENT_PENDING)
                    ->update(['status' => Order::PAYMENT_CANCELLED]);
            }

            $lockedOrder->update($updates);

            $lockedOrder->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $target,
                'actor_type' => 'system',
                'actor_id' => null,
                'comment' => 'Статус синхронизирован из iiko: ' . mb_substr((string) $iikoStatus, 0, 80) . '.',
                'created_at' => now(),
            ]);

            app(OrderNotificationService::class)
                ->notifyCustomerAboutStatusChange($lockedOrder, $target);

            return $target;
        }, 3);
    }
}
