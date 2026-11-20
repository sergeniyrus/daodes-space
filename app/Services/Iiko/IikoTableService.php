<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use RuntimeException;

class IikoTableService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function restaurantSections(IikoConnection $connection): array
    {
        if (!$connection->terminal_group_id) {
            throw new RuntimeException('Сначала выберите терминальную группу iiko.');
        }

        return $this->client->request(
            $connection,
            'POST',
            '/api/1/reserve/available_restaurant_sections',
            [
                'terminalGroupIds' => [$connection->terminal_group_id],
            ]
        );
    }
}
