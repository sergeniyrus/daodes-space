<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use InvalidArgumentException;
use RuntimeException;

class IikoLoyaltyService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function programs(IikoConnection $connection): array
    {
        return $this->client->request(
            $connection,
            'POST',
            '/api/1/loyalty/iiko/program',
            ['organizationId' => $this->organizationId($connection)]
        );
    }

    /**
     * Lookup by cardNumber, cardTrack, phone or iiko guest id.
     * Do not write lookup identifiers or returned personal data to integration logs.
     */
    public function customerInfo(
        IikoConnection $connection,
        string $type,
        string $identifier
    ): array {
        $fields = [
            'cardNumber' => 'cardNumber',
            'cardTrack' => 'cardTrack',
            'phone' => 'phone',
            'id' => 'id',
        ];

        if (!isset($fields[$type])) {
            throw new InvalidArgumentException('Неподдерживаемый способ поиска гостя iiko.');
        }

        if (trim($identifier) === '') {
            throw new InvalidArgumentException('Идентификатор гостя iiko не может быть пустым.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/loyalty/iiko/customer/info',
            [
                'organizationId' => $this->organizationId($connection),
                'type' => $type,
                $fields[$type] => trim($identifier),
            ]
        );
    }

    public function calculateDiscount(
        IikoConnection $connection,
        array $order
    ): array {
        if (!filled($order['phone'] ?? null) || !str_starts_with(trim((string) $order['phone']), '+')) {
            throw new InvalidArgumentException('Для расчёта скидки iiko требуется телефон в международном формате.');
        }

        if (empty($order['customer']['id']) || empty($order['items']) || (empty($order['orderServiceType']) && empty($order['orderTypeId']))) {
            throw new InvalidArgumentException('Для расчёта скидки iiko нужны гость, позиции и тип заказа.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/loyalty/iiko/calculate',
            [
                'organizationId' => $this->organizationId($connection),
                'order' => $order,
            ]
        );
    }

    public function transactionsByDate(
        IikoConnection $connection,
        string $customerId,
        string $dateFrom,
        string $dateTo,
        int $pageNumber = 0,
        int $pageSize = 100
    ): array {
        if ($pageNumber < 0 || $pageSize < 1 || $pageSize > 500) {
            throw new InvalidArgumentException('Некорректные параметры пагинации истории бонусов iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/loyalty/iiko/customer/transactions/by_date',
            [
                'organizationId' => $this->organizationId($connection),
                'customerId' => $customerId,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
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
