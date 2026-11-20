<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use InvalidArgumentException;
use RuntimeException;

class IikoAnalyticsService
{
    private const REPORT_TYPES = ['SALES', 'TRANSACTIONS', 'DELIVERIES'];

    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function columns(IikoConnection $connection, string $reportType = 'SALES'): array
    {
        $this->validateReportType($reportType);

        return $this->client->request(
            $connection,
            'POST',
            '/api/reporting/v1/olap/columns/get',
            ['reportType' => $reportType]
        );
    }

    public function runOlap(
        IikoConnection $connection,
        string $reportType,
        array $aggregateFields,
        array $filters,
        array $groupByRowFields = [],
        array $groupByColFields = []
    ): array {
        $this->validateReportType($reportType);

        if ($aggregateFields === []) {
            throw new InvalidArgumentException('Для OLAP-отчёта iiko укажите хотя бы одно агрегируемое поле.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/reporting/v1/olap/get',
            [
                'reportType' => $reportType,
                'aggregateFields' => array_values($aggregateFields),
                'filters' => array_values($filters),
                'groupByRowFields' => array_values($groupByRowFields),
                'groupByColFields' => array_values($groupByColFields),
            ]
        );
    }

    public function deliveriesByPeriod(
        IikoConnection $connection,
        string $dateFrom,
        string $dateTo,
        array $statuses = []
    ): array {
        if (!filled($connection->iiko_organization_id)) {
            throw new RuntimeException('Сначала выберите организацию iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/deliveries/by_delivery_date_and_status',
            [
                'organizationIds' => [$connection->iiko_organization_id],
                'deliveryDateFrom' => $dateFrom,
                'deliveryDateTo' => $dateTo,
                'statuses' => array_values($statuses),
            ]
        );
    }

    private function validateReportType(string $reportType): void
    {
        if (!in_array($reportType, self::REPORT_TYPES, true)) {
            throw new InvalidArgumentException('Неподдерживаемый тип OLAP-отчёта iiko.');
        }
    }
}
