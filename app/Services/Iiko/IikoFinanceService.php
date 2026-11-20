<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use RuntimeException;

class IikoFinanceService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function chartOfAccounts(IikoConnection $connection, int $limit = 100, int $offset = 0): array
    {
        return $this->listRequest(
            $connection,
            '/api/finance/v1/chart-of-accounts/list',
            $limit,
            $offset
        );
    }

    public function accounts(IikoConnection $connection, int $limit = 100, int $offset = 0): array
    {
        return $this->listRequest(
            $connection,
            '/api/finance/v1/account/list',
            $limit,
            $offset
        );
    }

    public function cashFlowCategories(IikoConnection $connection, int $limit = 100, int $offset = 0): array
    {
        return $this->listRequest(
            $connection,
            '/api/finance/v1/cash-flow-category/list',
            $limit,
            $offset
        );
    }

    public function balanceSheet(IikoConnection $connection, string $balanceAt): array
    {
        return $this->client->request(
            $connection,
            'POST',
            '/api/finance/v1/balance-sheet/list',
            [
                'organizationIds' => [$this->organizationId($connection)],
                'balanceAt' => $balanceAt,
            ]
        );
    }

    public function accountPostings(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        ?string $accountId = null
    ): array {
        $payload = [
            'organizationIds' => [$this->organizationId($connection)],
            'from' => $dateFrom,
            'to' => $dateTo,
        ];

        if (filled($accountId)) {
            $payload['accountId'] = $accountId;
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/finance/v1/account-posting/list',
            $payload
        );
    }

    public function accountTransactions(
        IikoConnection $connection,
        string $accountId,
        string $dateFrom,
        string $dateTo
    ): array {
        return $this->client->request(
            $connection,
            'POST',
            '/api/finance/v1/account_transactions/list',
            [
                'accountId' => $accountId,
                'organizationId' => $this->organizationId($connection),
                'from' => $dateFrom,
                'to' => $dateTo,
            ]
        );
    }

    public function documentTransactions(
        IikoConnection $connection,
        string $documentId
    ): array {
        return $this->client->request(
            $connection,
            'POST',
            '/api/finance/v1/document_transactions/list',
            [
                'documentId' => $documentId,
                'organizationId' => $this->organizationId($connection),
            ]
        );
    }

    private function listRequest(
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
                'filters' => [],
                'limit' => min(500, max(1, $limit)),
                'offset' => max(0, $offset),
            ]
        );
    }

    private function organizationId(IikoConnection $connection): string
    {
        if (!filled($connection->iiko_organization_id)) {
            throw new RuntimeException('Сначала выберите организацию iiko.');
        }

        return (string) $connection->iiko_organization_id;
    }
}
