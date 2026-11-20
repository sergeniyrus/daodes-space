<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use InvalidArgumentException;
use RuntimeException;

class IikoReservationService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function create(IikoConnection $connection, array $reservation): array
    {
        $required = [
            'customer',
            'phone',
            'guestsCount',
            'durationInMinutes',
            'shouldRemind',
            'tableIds',
            'estimatedStartTime',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $reservation) || $reservation[$field] === null || $reservation[$field] === '') {
                throw new InvalidArgumentException('Не заполнено обязательное поле бронирования iiko: ' . $field . '.');
            }
        }

        if (!is_array($reservation['customer']) || !filled($reservation['customer']['name'] ?? null)) {
            throw new InvalidArgumentException('Для бронирования iiko укажите имя гостя.');
        }

        if (!str_starts_with(trim((string) $reservation['phone']), '+')) {
            throw new InvalidArgumentException('Телефон для бронирования iiko должен начинаться с +.');
        }

        if ((int) $reservation['guestsCount'] < 1 || (int) $reservation['durationInMinutes'] < 15) {
            throw new InvalidArgumentException('Укажите корректное количество гостей и длительность бронирования.');
        }

        if (!is_array($reservation['tableIds']) || $reservation['tableIds'] === []) {
            throw new InvalidArgumentException('Для бронирования iiko выберите хотя бы один стол.');
        }

        if (empty($connection->terminal_group_id)) {
            throw new RuntimeException('Сначала выберите терминальную группу iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/reserve/create',
            array_merge($reservation, [
                'organizationId' => $this->organizationId($connection),
                'terminalGroupId' => $connection->terminal_group_id,
            ])
        );
    }


    public function commandStatus(IikoConnection $connection, string $correlationId): array
    {
        return $this->client->request(
            $connection,
            'POST',
            '/api/1/commands/status',
            [
                'organizationId' => $this->organizationId($connection),
                'correlationId' => $correlationId,
            ]
        );
    }

    public function statusById(IikoConnection $connection, array $reserveIds): array
    {
        if ($reserveIds === []) {
            throw new InvalidArgumentException('Не переданы идентификаторы бронирований iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/reserve/status_by_id',
            [
                'organizationId' => $connection->iiko_organization_id,
                'reserveIds' => array_values($reserveIds),
            ]
        );
    }

    public function cancel(
        IikoConnection $connection,
        string $reserveId,
        string $reason = 'Other'
    ): array {
        if (!in_array($reason, ['ClientNotAppeared', 'ClientRefused', 'Other'], true)) {
            throw new InvalidArgumentException('Недопустимая причина отмены бронирования iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/reserve/cancel',
            [
                'organizationId' => $connection->iiko_organization_id,
                'reserveId' => $reserveId,
                'cancelReason' => $reason,
            ]
        );
    }

    public function changeTables(
        IikoConnection $connection,
        string $reserveId,
        array $tableIds
    ): array {
        if ($tableIds === []) {
            throw new InvalidArgumentException('Для переноса бронирования выберите хотя бы один стол.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/reserve/change_tables',
            [
                'organizationId' => $connection->iiko_organization_id,
                'reserveId' => $reserveId,
                'tableIds' => array_values($tableIds),
            ]
        );
    }

    public function changeEstimatedStartTime(
        IikoConnection $connection,
        string $reserveId,
        string $estimatedStartTime
    ): array {
        return $this->client->request(
            $connection,
            'POST',
            '/api/1/reserve/change_estimated_start_time',
            [
                'organizationId' => $connection->iiko_organization_id,
                'reserveId' => $reserveId,
                'estimatedStartTime' => $estimatedStartTime,
            ]
        );
    }

    public function sectionWorkload(
        IikoConnection $connection,
        array $restaurantSectionIds,
        string $dateFrom,
        string $dateTo
    ): array {
        return $this->client->request(
            $connection,
            'POST',
            '/api/1/reserve/restaurant_sections_workload',
            [
                'organizationId' => $connection->iiko_organization_id,
                'restaurantSectionIds' => array_values($restaurantSectionIds),
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
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
