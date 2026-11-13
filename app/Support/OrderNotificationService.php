<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderNotification;
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
                'message' => 'Поступил заказ №' . $order->order_number . '. Требуется подтверждение.',
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
}
