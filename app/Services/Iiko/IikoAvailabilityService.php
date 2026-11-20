<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use App\Models\IikoMenuMapping;
use App\Models\IikoStopListItem;

class IikoAvailabilityService
{
    /**
     * Return DAODES menu item IDs whose mapped iiko products are currently stopped.
     * The integration is fail-open when stop-list synchronization is not enabled;
     * checkout still uses the last successfully synchronized snapshot.
     */
    public function stoppedMenuItemIds(int $organizationId): array
    {
        $enabled = IikoConnection::query()
            ->where('organization_id', $organizationId)
            ->where('stop_list_sync_enabled', true)
            ->exists();

        if (!$enabled) {
            return [];
        }

        return IikoMenuMapping::query()
            ->where('organization_id', $organizationId)
            ->whereIn('iiko_product_id', IikoStopListItem::query()
                ->select('iiko_product_id')
                ->where('organization_id', $organizationId)
                ->where('is_available', false))
            ->pluck('menu_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isMenuItemAvailable(int $organizationId, int $menuItemId): bool
    {
        return !in_array(
            $menuItemId,
            $this->stoppedMenuItemIds($organizationId),
            true
        );
    }
}
