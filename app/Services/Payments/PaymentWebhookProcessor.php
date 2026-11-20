<?php

namespace App\Services\Payments;

use App\Data\Payments\PaymentWebhookEvent as VerifiedPaymentWebhook;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\IikoConnection;
use App\Jobs\Iiko\SendIikoOrder;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentWebhookProcessor
{
    private const ALLOWED_STATUSES = [
        Order::PAYMENT_SUCCEEDED,
        Order::PAYMENT_FAILED,
        Order::PAYMENT_CANCELLED,
        Order::PAYMENT_REFUNDED,
    ];

    /**
     * Обрабатывает только уже проверенное адаптером провайдера уведомление.
     * Исходное тело не сохраняется: в журнал попадает только SHA-256.
     */
    public function process(
        string $routeProvider,
        VerifiedPaymentWebhook $event,
        string $rawPayload
    ): array {
        if (
            $event->provider !== $routeProvider
            || !in_array($event->status, self::ALLOWED_STATUSES, true)
            || $event->eventId === ''
            || $event->providerPaymentId === ''
        ) {
            throw new InvalidArgumentException('Некорректное платёжное событие.');
        }

        try {
            return DB::transaction(function () use ($routeProvider, $event, $rawPayload) {
                $existingEvent = PaymentWebhookEvent::query()
                    ->where('provider', $routeProvider)
                    ->where('event_id', $event->eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existingEvent) {
                    return [
                        'duplicate' => true,
                        'status' => $existingEvent->processing_status,
                    ];
                }

                $payment = OrderPayment::query()
                    ->where('provider', $routeProvider)
                    ->where('provider_payment_id', $event->providerPaymentId)
                    ->lockForUpdate()
                    ->first();

                $processingStatus = 'rejected';
                $processingMessage = 'Платёж не найден.';

                if ($payment) {
                    $order = Order::query()
                        ->whereKey($payment->order_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$order) {
                        $processingMessage = 'Заказ платежа не найден.';
                    } elseif (
                        !$this->sameAmount($payment->amount, $event->amount)
                        || strtoupper($payment->currency) !== strtoupper($event->currency)
                    ) {
                        $processingMessage = 'Сумма или валюта уведомления не совпадает с платежом.';
                    } else {
                        $transition = $this->applyPaymentStatus($payment, $order, $event->status);

                        $processingStatus = $transition['status'];
                        $processingMessage = $transition['message'];
                    }
                }

                PaymentWebhookEvent::query()->create([
                    'provider' => $routeProvider,
                    'event_id' => $event->eventId,
                    'payment_id' => $payment?->id,
                    'event_type' => $event->status,
                    'payload_hash' => hash('sha256', $rawPayload),
                    'processing_status' => $processingStatus,
                    'processing_message' => $processingMessage,
                    'processed_at' => now(),
                ]);

                return [
                    'duplicate' => false,
                    'status' => $processingStatus,
                    'message' => $processingMessage,
                ];
            }, 3);
        } catch (QueryException $exception) {
            // Уникальный индекс закрывает гонку двух одинаковых webhook-запросов.
            $existingEvent = PaymentWebhookEvent::query()
                ->where('provider', $routeProvider)
                ->where('event_id', $event->eventId)
                ->first();

            if ($existingEvent) {
                return [
                    'duplicate' => true,
                    'status' => $existingEvent->processing_status,
                ];
            }

            throw $exception;
        }
    }

    private function applyPaymentStatus(
        OrderPayment $payment,
        Order $order,
        string $newStatus
    ): array {
        if ($payment->status === $newStatus) {
            return [
                'status' => 'ignored',
                'message' => 'Платёж уже находится в этом состоянии.',
            ];
        }

        if ($newStatus === Order::PAYMENT_REFUNDED) {
            if ($payment->status !== Order::PAYMENT_SUCCEEDED) {
                return [
                    'status' => 'rejected',
                    'message' => 'Возврат возможен только для успешно оплаченного платежа.',
                ];
            }

            $payment->update([
                'status' => Order::PAYMENT_REFUNDED,
                'refunded_at' => now(),
            ]);

            $order->update([
                'payment_status' => Order::PAYMENT_REFUNDED,
            ]);

            return [
                'status' => 'processed',
                'message' => 'Возврат подтверждён провайдером.',
            ];
        }

        if ($payment->status !== Order::PAYMENT_PENDING) {
            return [
                'status' => 'rejected',
                'message' => 'Нельзя изменить конечное состояние платежа.',
            ];
        }

        if (
            $order->status === Order::STATUS_CANCELLED
            && $newStatus === Order::PAYMENT_SUCCEEDED
        ) {
            return [
                'status' => 'rejected',
                'message' => 'Провайдер подтвердил оплату отменённого заказа. Требуется ручная сверка.',
            ];
        }

        $timestamps = match ($newStatus) {
            Order::PAYMENT_SUCCEEDED => ['paid_at' => now()],
            Order::PAYMENT_FAILED => ['failed_at' => now()],
            Order::PAYMENT_CANCELLED => ['cancelled_at' => now()],
            default => [],
        };

        $payment->update(array_merge([
            'status' => $newStatus,
        ], $timestamps));

        $order->update([
            'payment_status' => $newStatus,
        ]);

        if (
            $newStatus === Order::PAYMENT_SUCCEEDED
            && $order->payment_method === Order::PAYMENT_ONLINE
            && in_array($order->fulfillment_type, [
                Order::FULFILLMENT_DELIVERY,
                Order::FULFILLMENT_PICKUP,
            ], true)
        ) {
            $connection = IikoConnection::query()
                ->where('organization_id', $order->organization_id)
                ->first();

            if (
                $connection?->is_enabled &&
                $connection?->order_sync_enabled &&
                config('iiko.allow_online_payment_orders')
            ) {
                SendIikoOrder::dispatch($order->id)->afterCommit();
            }
        }

        return [
            'status' => 'processed',
            'message' => 'Статус платежа обновлён по подтверждённому уведомлению.',
        ];
    }

    private function sameAmount(string|int|float $stored, string|int|float $received): bool
    {
        $storedMinor = $this->toMinorUnits($stored);
        $receivedMinor = $this->toMinorUnits($received);

        return $storedMinor !== null
            && $receivedMinor !== null
            && $storedMinor === $receivedMinor;
    }

    private function toMinorUnits(string|int|float $amount): ?int
    {
        $value = trim((string) $amount);

        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
