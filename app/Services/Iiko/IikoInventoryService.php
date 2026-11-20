<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use RuntimeException;

class IikoInventoryService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function storages(IikoConnection $connection, int $limit = 100, int $offset = 0): array
    {
        return $this->client->request(
            $connection,
            'POST',
            '/api/inventory/v2/storages/list',
            [
                'limit' => min(500, max(1, $limit)),
                'offset' => max(0, $offset),
            ]
        );
    }

    public function stockBalances(
        IikoConnection $connection,
        string $balanceAt,
        array $storeIds = [],
        array $productIds = [],
        array $productGroupIds = []
    ): array {
        if (!filled($connection->iiko_organization_id)) {
            throw new RuntimeException('Сначала выберите организацию iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/inventory/v1/stock_balance/list',
            array_filter([
                'organizationId' => $connection->iiko_organization_id,
                'balanceAt' => $balanceAt,
                'storeIds' => $storeIds !== [] ? array_values($storeIds) : null,
                'productIds' => $productIds !== [] ? array_values($productIds) : null,
                'productGroupIds' => $productGroupIds !== [] ? array_values($productGroupIds) : null,
                'shouldIncludeDetails' => true,
            ], fn ($value) => $value !== null)
        );
    }

    public function incomingInvoices(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails = false
    ): array {
        if (!filled($connection->iiko_organization_id)) {
            throw new RuntimeException('Сначала выберите организацию iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/inventory/v1/incoming_invoice/list',
            [
                'organizationId' => $connection->iiko_organization_id,
                'from' => $dateFrom,
                'to' => $dateTo,
                'returnFullDetails' => $returnFullDetails,
            ]
        );
    }
}
