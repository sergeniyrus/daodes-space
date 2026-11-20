<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use RuntimeException;

class IikoWebhookService
{
    public function __construct(
        private readonly IikoCloudClient $client
    ) {
    }

    public function configure(IikoConnection $connection): array
    {
        $url = (string) config('iiko.webhook_url');
        $secret = (string) $connection->webhook_secret;

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            throw new RuntimeException('Не настроен публичный HTTPS URL вебхука iiko в конфигурации DAODES.');
        }

        if ($secret === '') {
            throw new RuntimeException('Не создан секрет проверки подлинности вебхука iiko.');
        }

        return $this->client->request($connection, 'POST', '/api/1/webhooks/update_settings', [
            'organizationId' => $connection->iiko_organization_id,
            'webHooksUri' => $url,
            'authToken' => $secret,
        ]);
    }

    public function settings(IikoConnection $connection): array
    {
        return $this->client->request($connection, 'POST', '/api/1/webhooks/settings', [
            'organizationId' => $connection->iiko_organization_id,
        ]);
    }
}
