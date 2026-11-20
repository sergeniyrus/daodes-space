<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;

class IikoMenuService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    /**
     * Return external menus configured for the selected iiko organization.
     */
    public function externalMenus(IikoConnection $connection): array
    {
        $this->assertOrganizationSelected($connection);

        return $this->client->request($connection, 'POST', '/api/2/menu', [
            'organizationIds' => [$connection->iiko_organization_id],
        ]);
    }

    /**
     * Return one full external menu. This method is read-only.
     */
    public function externalMenu(
        IikoConnection $connection,
        string $externalMenuId,
        ?string $priceCategoryId = null
    ): array {
        $this->assertOrganizationSelected($connection);

        $payload = [
            'externalMenuId' => $externalMenuId,
            'organizationId' => $connection->iiko_organization_id,
        ];

        if ($priceCategoryId !== null && $priceCategoryId !== '') {
            $payload['priceCategoryId'] = $priceCategoryId;
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/menu/v3/by_id',
            $payload
        );
    }

    /**
     * Return the current stop list for the selected organization.
     */
    public function stopList(IikoConnection $connection): array
    {
        $this->assertOrganizationSelected($connection);

        return $this->client->request($connection, 'POST', '/api/1/stop_lists', [
            'organizationIds' => [$connection->iiko_organization_id],
        ]);
    }

    private function assertOrganizationSelected(IikoConnection $connection): void
    {
        if (!$connection->iiko_organization_id) {
            throw new \RuntimeException('Сначала выберите организацию iiko.');
        }
    }
}
