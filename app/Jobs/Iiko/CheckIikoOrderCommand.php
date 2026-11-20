<?php

namespace App\Jobs\Iiko;

use App\Models\IikoOrderMapping;
use App\Models\Order;
use App\Services\Iiko\IikoOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CheckIikoOrderCommand implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 8;

    public array $backoff = [5, 10, 15, 30, 60, 120, 180, 300];

    public int $timeout = 60;

    public function __construct(
        public readonly int $orderId,
        public readonly int $pollCount = 1
    ) {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('iiko-order-check-' . $this->orderId))
                ->expireAfter(90)
                ->dontRelease(),
        ];
    }

    public function handle(IikoOrderService $orderService): void
    {
        $mapping = IikoOrderMapping::query()
            ->with('organization')
            ->where('order_id', $this->orderId)
            ->first();

        if (!$mapping || !$mapping->correlation_id) {
            return;
        }

        $connection = $mapping->organization?->iikoConnection;

        if (!$connection) {
            return;
        }

        if ((string) $mapping->iiko_organization_id !== (string) $connection->iiko_organization_id) {
            $mapping->update([
                'sync_status' => 'processing',
                'last_error' => 'Подключение iiko изменилось. Проверка старой команды остановлена; требуется ручная сверка в исходной организации.',
            ]);

            return;
        }

        $response = $orderService->commandStatus($connection, $mapping->correlation_id);
        $state = (string) ($response['state'] ?? $response['status'] ?? '');

        $mapping->update([
            'iiko_status' => $state !== '' ? $state : null,
            'last_synced_at' => now(),
        ]);

        if (in_array(mb_strtolower($state), ['completed', 'success', 'succeeded'], true)) {
            $mapping->update([
                'sync_status' => 'sent',
                'last_error' => null,
                'sent_at' => $mapping->sent_at ?? now(),
            ]);

            return;
        }

        if (in_array(mb_strtolower($state), ['failed', 'error', 'cancelled'], true)) {
            $mapping->update([
                'sync_status' => 'failed',
                'last_error' => 'iiko не смогла обработать команду создания заказа.',
            ]);

            return;
        }

        // Unknown or in-progress states are checked again using queue backoff.
        if ($this->pollCount >= 8) {
            $mapping->update([
                'sync_status' => 'processing',
                'last_error' => 'iiko ещё не подтвердила результат создания заказа. Повторная отправка заблокирована; требуется продолжить сверку.',
            ]);
        } else {
            self::dispatch($this->orderId, $this->pollCount + 1)
                ->delay(now()->addSeconds(15));
        }
    }

    public function failed(Throwable $exception): void
    {
        IikoOrderMapping::query()
            ->where('order_id', $this->orderId)
            ->whereNotNull('correlation_id')
            ->update([
                'sync_status' => 'processing',
                'last_error' => 'iiko приняла запрос, но автоматическая проверка результата завершилась ошибкой. Повторная отправка заблокирована.',
            ]);
    }
}
