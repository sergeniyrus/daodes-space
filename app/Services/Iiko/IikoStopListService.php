<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use App\Models\IikoStopListItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IikoStopListService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    /**
     * Replace the local stop-list snapshot only after iiko returns successfully.
     * A failed API call never clears the last known snapshot.
     */
    public function synchronize(IikoConnection $connection): int
    {
        if (!$connection->iiko_organization_id) {
            throw new RuntimeException('Сначала выберите организацию iiko.');
        }

        $payload = $this->client->request($connection, 'POST', '/api/1/stop_lists', [
            'organizationIds' => [$connection->iiko_organization_id],
            'terminalGroupsIds' => $connection->terminal_group_id
                ? [$connection->terminal_group_id]
                : [],
        ]);

        $groups = collect($payload['terminalGroupStopLists'] ?? []);
        $items = $groups->flatMap(fn ($group) =>
            is_array($group) ? collect($group['items'] ?? []) : []
        );

        // Some API response variants may expose the items at the root.
        if ($items->isEmpty() && isset($payload['items']) && is_array($payload['items'])) {
            $items = collect($payload['items']);
        }

        $syncedAt = now();
        $rows = $items
            ->filter(fn ($item) => is_array($item) && filled($item['productId'] ?? null))
            ->map(fn ($item) => [
                'organization_id' => $connection->organization_id,
                'iiko_product_id' => (string) $item['productId'],
                'balance' => (float) ($item['balance'] ?? 0),
                'is_available' => false,
                'synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ])
            ->unique('iiko_product_id')
            ->values();

        DB::transaction(function () use ($connection, $rows, $syncedAt) {
            IikoStopListItem::query()
                ->where('organization_id', $connection->organization_id)
                ->delete();

            if ($rows->isNotEmpty()) {
                IikoStopListItem::query()->insert($rows->all());
            }

            $connection->update([
                'last_stop_list_sync_at' => $syncedAt,
                'stop_list_sync_enabled' => true,
                'last_error' => null,
            ]);
        });

        return $rows->count();
    }
}
