<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use InvalidArgumentException;
use RuntimeException;

class IikoTableOrderService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    /**
     * Create a dine-in order on the selected POS terminal group.
     *
     * This method is deliberately not dispatched automatically: the iiko
     * table-order endpoint does not document the same idempotency contract as
     * delivery orders. A timeout must be reconciled before any manual retry.
     */
    public function create(
        IikoConnection $connection,
        array $tableIds,
        array $items,
        ?string $comment = null
    ): array {
        if (!filled($connection->iiko_organization_id) || !filled($connection->terminal_group_id)) {
            throw new RuntimeException('Сначала выберите организацию и терминальную группу iiko.');
        }

        if ($tableIds === []) {
            throw new InvalidArgumentException('Для заказа в зале необходимо выбрать стол.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('Заказ в зале должен содержать хотя бы одно блюдо.');
        }

        foreach ($items as $item) {
            if (
                !is_array($item) ||
                !filled($item['productId'] ?? null) ||
                !is_numeric($item['amount'] ?? null) ||
                (float) $item['amount'] <= 0
            ) {
                throw new InvalidArgumentException('Некорректная позиция заказа в зале iiko.');
            }
        }

        $order = [
            'orderServiceType' => 'Common',
            'tableIds' => array_values($tableIds),
            'items' => array_values($items),
        ];

        if (filled($comment)) {
            $order['comment'] = mb_substr(trim($comment), 0, 500);
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/order/create',
            [
                'organizationId' => $connection->iiko_organization_id,
                'terminalGroupId' => $connection->terminal_group_id,
                'order' => $order,
            ]
        );
    }

    public function commandStatus(
        IikoConnection $connection,
        string $correlationId
    ): array {
        if (!filled($connection->iiko_organization_id)) {
            throw new RuntimeException('Сначала выберите организацию iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/commands/status',
            [
                'organizationId' => $connection->iiko_organization_id,
                'correlationId' => $correlationId,
            ]
        );
    }
}
