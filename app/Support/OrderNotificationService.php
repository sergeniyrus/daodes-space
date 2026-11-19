<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderNotification;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;

class OrderNotificationService
{
    /**
     * Уведомляет активных менеджеров организации о новом заказе.
     *
     * Вызывается внутри транзакции создания заказа.
     */
    public function notifyManagersAboutNewOrder(Order $order): void
    {
        $managerIds = DB::table('organization_users')
            ->where('organization_id', $order->organization_id)
            ->where('role', 'manager')
            ->where('status', 'active')
            ->pluck('user_id');

        foreach ($managerIds as $managerId) {
            OrderNotification::query()->create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'recipient_type' => 'user',
                'recipient_id' => $managerId,
                'event_type' => 'new_order',
                'title' => 'Новый заказ',
                'message' => 'Поступил новый заказ №' . $order->order_number . '. Он ожидает принятия поваром.',
            ]);
        }

        // Администраторы организации также получают уведомление о новом заказе.
        $adminIds = DB::table('organization_employee_memberships')
            ->where('organization_id', $order->organization_id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->pluck('employee_id');

        foreach ($adminIds as $adminId) {
            OrderNotification::query()->create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'recipient_type' => 'employee',
                'recipient_id' => $adminId,
                'event_type' => 'new_order',
                'title' => 'Новый заказ',
                'message' => 'Поступил новый заказ №' . $order->order_number . '.',
            ]);
        }
    }

    /**
     * Уведомляет сотрудника о назначении на заказ.
     *
     * Вызывается в той же транзакции, что и создание назначения.
     */
    public function notifyEmployeeAssigned(
        Order $order,
        int $employeeId,
        string $role
    ): void {
        $roleLabel = $role === 'cook' ? 'повар' : 'курьер';

        OrderNotification::query()->create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'recipient_type' => 'employee',
            'recipient_id' => $employeeId,
            'event_type' => 'order_assigned',
            'title' => 'Новый заказ назначен вам',
            'message' => 'Заказ №' . $order->order_number . ' назначен вам (' . $roleLabel . ').',
        ]);
    }

    /**
     * Уведомляет клиента об изменении статуса его заказа.
     *
     * Вызывается внутри транзакции перехода статуса.
     */
    public function notifyCustomerAboutStatusChange(Order $order, string $status): void
    {
        if (!$order->customer_id) {
            return;
        }

        $statusLabels = [
            Order::STATUS_NEW => 'Новый',
            Order::STATUS_AWAITING_PAYMENT => 'Ожидает оплаты',
            Order::STATUS_CONFIRMED => 'Подтверждён',
            Order::STATUS_PREPARING => 'Готовится',
            Order::STATUS_READY => 'Готов',
            Order::STATUS_HANDED_TO_COURIER => 'Передан курьеру',
            Order::STATUS_IN_TRANSIT => 'В пути',
            Order::STATUS_ISSUED => 'Выдан',
            Order::STATUS_SERVED => 'Подан',
            Order::STATUS_DELIVERED => 'Доставлен',
            Order::STATUS_COMPLETED => 'Завершён',
            Order::STATUS_CANCELLED => 'Отменён',
        ];

        $statusLabel = $statusLabels[$status] ?? $status;

        $title = 'Статус заказа обновлён';
        $message = 'Заказ №' . $order->order_number . ': ' . $statusLabel . '.';

        OrderNotification::query()->create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'recipient_type' => 'customer',
            'recipient_id' => $order->customer_id,
            'event_type' => 'order_status_changed',
            'title' => $title,
            'message' => $message,
        ]);

        app(PushNotificationService::class)->notifyOrderStatus($order, $status, $title, $message);

        if ($status === Order::STATUS_READY) {
            $this->sendReadySmsAfterCommit($order);
        }
    }

    /**
     * Выдаёт ссылку на электронный чек и отправляет SMS только после
     * фиксации транзакции смены статуса.
     */
    private function sendReadySmsAfterCommit(Order $order): void
    {
        if (!$order->customer_phone) {
            return;
        }

        $token = \Illuminate\Support\Str::random(64);

        $order->forceFill([
            'receipt_token_hash' => hash('sha256', $token),
            'receipt_token_expires_at' => now()->addDays(30),
        ])->save();

        $orderId = (int) $order->id;

        DB::afterCommit(function () use ($orderId, $token): void {
            $readyOrder = Order::query()->find($orderId);

            if (!$readyOrder) {
                return;
            }

            try {
                $receiptUrl = route('customer.receipt', ['token' => $token]);

                app(\App\Contracts\SmsTransport::class)->send(
                    $readyOrder->customer_phone,
                    'Заказ №' . $readyOrder->order_number
                        . ' готов! Электронный чек: ' . $receiptUrl
                );
            } catch (\Throwable $exception) {
                // Ошибка SMS не должна откатывать уже сохранённый статус заказа.
                \Illuminate\Support\Facades\Log::warning('Order ready SMS was not sent.', [
                    'order_id' => $orderId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
