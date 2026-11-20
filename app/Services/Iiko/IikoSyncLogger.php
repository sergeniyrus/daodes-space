<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use App\Models\IikoSyncLog;
use Carbon\CarbonInterface;

class IikoSyncLogger
{
    public function record(
        IikoConnection $connection,
        string $type,
        string $status,
        string $message,
        ?string $correlationId = null,
        array $context = [],
        ?CarbonInterface $startedAt = null
    ): IikoSyncLog {
        // Keep logs operational and avoid storing credentials, customer details,
        // delivery addresses or complete API request/response bodies.
        $safeContext = collect($context)
            ->only([
                'endpoint',
                'http_status',
                'items_count',
                'organizations_count',
                'terminal_groups_count',
                'external_menu_id',
                'order_id',
                'attempt',
            ])
            ->all();

        return IikoSyncLog::query()->create([
            'organization_id' => $connection->organization_id,
            'iiko_connection_id' => $connection->id,
            'sync_type' => $type,
            'status' => $status,
            'message' => mb_substr($message, 0, 500),
            'correlation_id' => $correlationId,
            'context' => $safeContext,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }
}
