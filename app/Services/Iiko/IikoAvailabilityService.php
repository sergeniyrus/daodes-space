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
        $connection = IikoConnection::query()
            ->where('organization_id', $organizationId)
            ->where('stop_list_sync_enabled', true)
            ->first(['id', 'iiko_organization_id']);

        if (!$connection || !$connection->iiko_organization_id) {
            return [];
        }

        return IikoMenuMapping::query()
            ->where('organization_id', $organizationId)
            ->where('iiko_organization_id', $connection->iiko_organization_id)
            ->where('external_menu_id', $connection->external_menu_id)
            ->where('price_category_id', $connection->price_category_id)
            ->whereIn('iiko_product_id', IikoStopListItem::query()
                ->select('iiko_product_id')
                ->where('organization_id', $organizationId)
                ->where('iiko_organization_id', $connection->iiko_organization_id)
                ->where('terminal_group_id', $connection->terminal_group_id)
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
