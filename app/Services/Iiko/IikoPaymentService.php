<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;

class IikoPaymentService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function paymentTypes(IikoConnection $connection): array
    {
        if (!$connection->iiko_organization_id) {
            throw new \RuntimeException('Сначала выберите организацию iiko.');
        }

        return $this->client->request($connection, 'POST', '/api/1/payment_types', [
            'organizationIds' => [$connection->iiko_organization_id],
        ]);
    }
}
