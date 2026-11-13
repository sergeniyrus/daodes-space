<?php

namespace App\Support;

use App\Models\Order;

/**
 * Единая политика переходов статусов заказов.
 *
 * Класс не выполняет запросов и не меняет модели: правила можно
 * проверять отдельно от HTTP-контроллеров и базы данных.
 */
class OrderTransitionPolicy
{
    public static function managerError(
        string $fromStatus,
        string $toStatus,
        string $fulfillmentType,
        string $paymentStatus
    ): ?string {
        return match ($toStatus) {
            Order::STATUS_SERVED => (
                $fromStatus === Order::STATUS_READY
                && $fulfillmentType === Order::FULFILLMENT_DINE_IN
            )
                ? null
                : 'Отметить подачу можно только для готового заказа в заведении.',

            Order::STATUS_COMPLETED => (
                in_array($fromStatus, [
                    Order::STATUS_DELIVERED,
                    Order::STATUS_ISSUED,
                    Order::STATUS_SERVED,
                ], true)
                && $paymentStatus === Order::PAYMENT_SUCCEEDED
            )
                ? null
                : (
                    !in_array($fromStatus, [
                        Order::STATUS_DELIVERED,
                        Order::STATUS_ISSUED,
                        Order::STATUS_SERVED,
                    ], true)
                        ? 'Завершить можно только доставленный или выданный заказ.'
                        : 'Сначала отметьте получение оплаты.'
                ),

            Order::STATUS_CANCELLED => (
                in_array($fromStatus, [
                    Order::STATUS_NEW,
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PREPARING,
                    Order::STATUS_READY,
                ], true)
                && $paymentStatus !== Order::PAYMENT_SUCCEEDED
            )
                ? null
                : (
                    $paymentStatus === Order::PAYMENT_SUCCEEDED
                        ? 'Заказ уже оплачен. Перед отменой необходимо реализовать и выполнить возврат средств.'
                        : 'Этот заказ уже нельзя отменить из панели менеджера.'
                ),

            default => 'Недопустимый переход статуса заказа.',
        };
    }

    public static function employeeError(
        string $role,
        string $fromStatus,
        string $toStatus,
        string $fulfillmentType,
        bool $activeCourierAssigned,
        ?string $paymentStatus = null
    ): ?string {
        if ($role === 'cook') {
            $allowed = (
                in_array($fromStatus, [Order::STATUS_NEW, Order::STATUS_CONFIRMED], true)
                && $toStatus === Order::STATUS_PREPARING
            ) || (
                $fromStatus === Order::STATUS_PREPARING
                && $toStatus === Order::STATUS_READY
            );

            return $allowed
                ? null
                : 'Переход статуса недоступен для повара или текущего состояния заказа.';
        }

        if ($role === 'courier') {
            if ($fulfillmentType !== Order::FULFILLMENT_DELIVERY) {
                return 'Курьер может обрабатывать только заказы с доставкой.';
            }

            $allowed = (
                $fromStatus === Order::STATUS_HANDED_TO_COURIER
                && $toStatus === Order::STATUS_IN_TRANSIT
            ) || (
                $fromStatus === Order::STATUS_IN_TRANSIT
                && $toStatus === Order::STATUS_DELIVERED
            ) || (
                $fromStatus === Order::STATUS_DELIVERED
                && $toStatus === Order::STATUS_COMPLETED
                && $paymentStatus === Order::PAYMENT_SUCCEEDED
            );

            return $allowed
                ? null
                : 'Переход статуса недоступен для курьера или текущего состояния заказа.';
        }

        return 'У сотрудника нет роли для изменения статуса заказа.';
    }
}
