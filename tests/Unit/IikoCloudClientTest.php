<?php

namespace Tests\Unit;

use App\Exceptions\IikoApiException;
use App\Models\IikoConnection;
use App\Services\Iiko\IikoAuthService;
use App\Services\Iiko\IikoCloudClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IikoCloudClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'iiko.base_url' => 'https://api-ru.iiko.services',
            'iiko.app_id' => 'daodes-app',
            'iiko.client_secret' => 'daodes-secret',
            'iiko.timeout' => 10,
            'iiko.connect_timeout' => 3,
            'iiko.token_cache_minutes' => 55,
        ]);

        Cache::store('array')->flush();
    }

    public function test_authentication_uses_application_credentials_and_organization_api_key(): void
    {
        Http::fake([
            'https://api-ru.iiko.services/api/v2/access_token' => Http::response([
                'token' => 'test-jwt-token',
                'correlationId' => 'test-correlation',
            ], 200),
        ]);

        $connection = $this->connection();
        $token = app(IikoAuthService::class)->token($connection);

        $this->assertSame('test-jwt-token', $token);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api-ru.iiko.services/api/v2/access_token'
                && $request['appId'] === 'daodes-app'
                && $request['clientSecret'] === 'daodes-secret'
                && $request['apiKey'] === 'restaurant-api-key';
        });
    }

    public function test_access_token_is_cached_for_the_connection(): void
    {
        Http::fake([
            'https://api-ru.iiko.services/api/v2/access_token' => Http::response([
                'token' => 'cached-jwt-token',
            ], 200),
        ]);

        $connection = $this->connection();
        $service = app(IikoAuthService::class);

        $this->assertSame('cached-jwt-token', $service->token($connection));
        $this->assertSame('cached-jwt-token', $service->token($connection));

        Http::assertSentCount(1);
    }

    public function test_cloud_client_renews_token_once_after_unauthorized_response(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'expired-jwt-token'], 200)
            ->push(['message' => 'Unauthorized'], 401)
            ->push(['token' => 'renewed-jwt-token'], 200)
            ->push(['organizations' => []], 200);

        $connection = $this->connection();

        $result = app(IikoCloudClient::class)->request(
            $connection,
            'POST',
            '/api/1/organizations'
        );

        $this->assertSame(['organizations' => []], $result);
        Http::assertSentCount(4);
    }

    public function test_authentication_failure_throws_a_safe_api_exception(): void
    {
        Http::fake([
            'https://api-ru.iiko.services/api/v2/access_token' => Http::response([
                'error' => 'invalid credentials',
            ], 401),
        ]);

        try {
            app(IikoAuthService::class)->token($this->connection());
            $this->fail('Expected iiko authentication to fail.');
        } catch (IikoApiException $exception) {
            $this->assertSame(401, $exception->statusCode);
            $this->assertSame('Не удалось получить токен iikoCloud.', $exception->getMessage());
        }
    }

    private function connection(): IikoConnection
    {
        $connection = new IikoConnection([
            'organization_id' => 12,
            'api_key' => 'restaurant-api-key',
        ]);

        $connection->id = 34;

        return $connection;
    }
}
