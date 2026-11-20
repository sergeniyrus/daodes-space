<?php

namespace App\Jobs\Iiko;

use App\Models\IikoOrderMapping;
use App\Models\IikoWebhookEvent;
use App\Services\Iiko\IikoStopListService;
use App\Services\Iiko\IikoSyncLogger;
use App\Services\Iiko\IikoOrderStatusSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessIikoWebhookEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 180, 300];

    public int $timeout = 90;

    public function __construct(
        public readonly int $eventId
    ) {
    }

    public function handle(
        IikoStopListService $stopListService,
        IikoSyncLogger $logger,
        IikoOrderStatusSyncService $statusSyncService
    ): void {
        $event = IikoWebhookEvent::query()
            ->with('connection')
            ->find($this->eventId);

        if (!$event || $event->processing_status === 'processed') {
            return;
        }

        $connection = $event->connection;

        if (!$connection) {
            $event->update([
                'processing_status' => 'failed',
                'error_message' => 'Подключение iiko больше не существует.',
            ]);

            return;
        }

        if (
            $event->iiko_organization_id !== $connection->iiko_organization_id ||
            $event->terminal_group_id !== $connection->terminal_group_id
        ) {
            $event->update([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'error_message' => 'Событие пропущено: подключение iiko или терминальная группа изменились после его получения.',
            ]);

            return;
        }

        try {
            if ($event->event_type === 'StopListUpdate') {
                $stopListService->synchronize($connection);
            } elseif (
                $event->event_type === 'DeliveryOrderUpdate' &&
                $connection->status_sync_enabled
            ) {
                $mapping = filled($event->iiko_order_id)
                    ? IikoOrderMapping::query()
                        ->where('organization_id', $event->organization_id)
                        ->where('iiko_organization_id', $connection->iiko_organization_id)
                        ->where('iiko_order_id', $event->iiko_order_id)
                        ->first()
                    : null;

                if ($mapping) {
                    $mapping->update([
                        'iiko_status' => $event->event_status,
                        'last_synced_at' => now(),
                    ]);

                    $statusSyncService->synchronize($mapping, $event->event_status);
                }
            } elseif (
                $event->event_type === 'DeliveryOrderError' &&
                $connection->order_sync_enabled
            ) {
                if (filled($event->iiko_order_id)) {
                    IikoOrderMapping::query()
                        ->where('organization_id', $event->organization_id)
                        ->where('iiko_organization_id', $connection->iiko_organization_id)
                        ->where('iiko_order_id', $event->iiko_order_id)
                        ->update([
                        'sync_status' => 'failed',
                        'last_error' => 'iiko сообщила об ошибке сохранения заказа.',
                            'last_synced_at' => now(),
                        ]);
                }
            }

            $event->update([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            $logger->record(
                $connection,
                'webhook',
                'success',
                'Событие iiko обработано.',
                null,
                ['endpoint' => $event->event_type]
            );
        } catch (Throwable $exception) {
            $event->update([
                'processing_status' => 'pending',
                'error_message' => 'Ошибка фоновой обработки события iiko.',
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        IikoWebhookEvent::query()
            ->whereKey($this->eventId)
            ->update([
                'processing_status' => 'failed',
                'error_message' => 'Не удалось обработать событие iiko после повторных попыток.',
                'updated_at' => now(),
            ]);
    }
}
