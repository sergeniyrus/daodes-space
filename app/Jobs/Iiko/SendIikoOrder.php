<?php

namespace App\Jobs\Iiko;

use App\Models\IikoOrderMapping;
use App\Models\Order;
use App\Exceptions\IikoApiException;
use Illuminate\Http\Client\ConnectionException;
use App\Services\Iiko\IikoOrderService;
use App\Services\Iiko\IikoSyncLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendIikoOrder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 180, 300];

    public int $timeout = 90;

    public function __construct(
        public readonly int $orderId
    ) {
    }

    /**
     * Do not let duplicate queue deliveries submit the same order concurrently.
     * The lock expires after the maximum job runtime plus a safety margin.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('iiko-order-' . $this->orderId))
                ->expireAfter(180)
                ->dontRelease(),
        ];
    }

    public function handle(IikoOrderService $orderService): void
    {
        $order = Order::query()
            ->with(['items', 'organization.iikoConnection', 'organization.orderSettings'])
            ->find($this->orderId);

        if (!$order) {
            return;
        }

        $connection = $order->organization?->iikoConnection;

        if (!$connection || !$connection->is_enabled || !$connection->order_sync_enabled) {
            return;
        }

        $mapping = IikoOrderMapping::query()->firstOrCreate(
            ['order_id' => $order->id],
            [
                'organization_id' => $order->organization_id,
                'iiko_organization_id' => $connection->iiko_organization_id,
                'sync_status' => 'pending',
            ]
        );

        if (
            filled($mapping->iiko_organization_id) &&
            $mapping->iiko_organization_id !== $connection->iiko_organization_id
        ) {
            $mapping->update([
                'sync_status' => 'failed',
                'last_error' => 'Подключение iiko изменилось после создания этого заказа. Требуется ручная сверка; автоматическая повторная отправка запрещена.',
            ]);

            return;
        }

        if (!$mapping->iiko_organization_id) {
            $mapping->update([
                'iiko_organization_id' => $connection->iiko_organization_id,
            ]);
        }

        // Once iiko has returned a correlationId, the create command is already
        // accepted for asynchronous processing. Never call deliveries/create again
        // while its result is pending; resume status polling instead.
        if (in_array($mapping->sync_status, ['sent', 'processing'], true)) {
            if ($mapping->sync_status === 'processing' && filled($mapping->correlation_id)) {
                CheckIikoOrderCommand::dispatch($order->id);
            }

            return;
        }

        if (filled($mapping->correlation_id)) {
            $mapping->update(['sync_status' => 'processing']);
            CheckIikoOrderCommand::dispatch($order->id);

            return;
        }

        $mapping->increment('attempts');

        try {
            $response = $orderService->send($order, $connection);

            $mapping->update([
                'iiko_order_id' => data_get($response, 'orderInfo.id')
                    ?? data_get($response, 'order.id')
                    ?? ($response['orderId'] ?? null)
                    ?? ($response['id'] ?? null),
                'correlation_id' => $response['correlationId'] ?? null,
                'sync_status' => 'processing',
                'last_error' => filled($response['correlationId'] ?? null)
                    ? null
                    : 'iiko приняла запрос, но не вернула correlationId. Повторная отправка заблокирована до ручной сверки.',

                'sent_at' => now(),
                'request_hash' => hash('sha256', $order->idempotency_key),
            ]);

            if (filled($response['correlationId'] ?? null)) {
                CheckIikoOrderCommand::dispatch($order->id)
                    ->delay(now()->addSeconds(5));
            }
        } catch (Throwable $exception) {
            $message = mb_substr($this->safeError($exception), 0, 2000);

            $retryable = $exception instanceof ConnectionException
                || (
                    $exception instanceof IikoApiException &&
                    ($exception->statusCode === 429 || $exception->statusCode >= 500)
                );

            if (!$retryable) {
                $mapping->update([
                    'sync_status' => 'failed',
                    'last_error' => $message,
                ]);

                app(IikoSyncLogger::class)->record(
                    $connection,
                    'order_send',
                    'failed',
                    $message,
                    null,
                    ['order_id' => $order->id]
                );

                return;
            }

            $mapping->update([
                'sync_status' => 'pending',
                'last_error' => $message,
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $mapping = IikoOrderMapping::query()
            ->where('order_id', $this->orderId)
            ->first();

        if (!$mapping) {
            return;
        }

        $mapping->update([
            'sync_status' => filled($mapping->correlation_id) ? 'processing' : 'failed',
            'last_error' => filled($mapping->correlation_id)
                ? 'Запрос уже принят iiko; проверка результата не завершилась. Повторная отправка заблокирована.'
                : mb_substr($this->safeError($exception), 0, 2000),
        ]);

        $order = Order::query()->with('organization.iikoConnection')->find($this->orderId);
        $connection = $order?->organization?->iikoConnection;

        if ($connection) {
            app(IikoSyncLogger::class)->record(
                $connection,
                'order_send',
                'failed',
                $this->safeError($exception),
                null,
                ['order_id' => $this->orderId]
            );
        }
    }

    private function safeError(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \App\Exceptions\IikoApiException => match ($exception->statusCode) {
                401 => 'iiko отклонила авторизацию.',
                403 => 'У API Key нет прав на создание заказов.',
                402 => 'Для организации не подключён необходимый модуль iiko.',
                429 => 'iiko ограничила частоту запросов.',
                default => 'Ошибка API iiko при передаче заказа.',
            },
            default => $exception->getMessage(),
        };
    }
}
