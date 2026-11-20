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

    public function outgoingInvoices(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails = false
    ): array {
        return $this->documentList($connection, '/api/inventory/v1/outgoing_invoice/list', $dateFrom, $dateTo, $returnFullDetails);
    }

    public function writeoffDocuments(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails = false
    ): array {
        return $this->documentList($connection, '/api/inventory/v2/writeoff_document/list', $dateFrom, $dateTo, $returnFullDetails);
    }

    public function productionDocuments(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails = false
    ): array {
        return $this->documentList($connection, '/api/inventory/v2/production_document/list', $dateFrom, $dateTo, $returnFullDetails);
    }

    public function disassemblyDocuments(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails = false
    ): array {
        return $this->documentList($connection, '/api/inventory/v2/disassemble_document/list', $dateFrom, $dateTo, $returnFullDetails);
    }

    public function internalTransfers(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails = false
    ): array {
        return $this->documentList($connection, '/api/inventory/v2/internal_transfer/list', $dateFrom, $dateTo, $returnFullDetails);
    }

    public function transformations(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails = false
    ): array {
        return $this->documentList($connection, '/api/inventory/v1/transformation_document/list', $dateFrom, $dateTo, $returnFullDetails);
    }

    public function counteragents(IikoConnection $connection, int $limit = 100, int $offset = 0): array
    {
        return $this->referenceList($connection, '/api/inventory/v1/counteragents/list', $limit, $offset);
    }

    public function measureUnits(IikoConnection $connection, int $limit = 100, int $offset = 0): array
    {
        return $this->referenceList($connection, '/api/inventory/v1/measure_units/list', $limit, $offset);
    }

    private function documentList(
        IikoConnection $connection,
        string $path,
        string $dateFrom,
        string $dateTo,
        bool $returnFullDetails
    ): array {
        if (!filled($connection->iiko_organization_id)) {
            throw new RuntimeException('Сначала выберите организацию iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            $path,
            [
                'organizationId' => $connection->iiko_organization_id,
                'from' => $dateFrom,
                'to' => $dateTo,
                'returnFullDetails' => $returnFullDetails,
            ]
        );
    }

    private function referenceList(
        IikoConnection $connection,
        string $path,
        int $limit,
        int $offset
    ): array {
        return $this->client->request(
            $connection,
            'POST',
            $path,
            [
                'limit' => min(500, max(1, $limit)),
                'offset' => max(0, $offset),
            ]
        );
    }

}
