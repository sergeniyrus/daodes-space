<?php

namespace Tests\Unit;

use App\Models\IikoConnection;
use App\Services\Iiko\IikoMenuService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IikoMenuServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'iiko.base_url' => 'https://api-ru.iiko.services',
            'iiko.app_id' => 'daodes-app',
            'iiko.client_secret' => 'daodes-secret',
            'iiko.timeout' => 10,
            'iiko.connect_timeout' => 3,
            'iiko.token_cache_minutes' => 55,
        ]);

        Cache::flush();
    }

    public function test_external_menus_are_requested_for_the_selected_organization(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push([
                'externalMenus' => [
                    ['id' => 'e4e0e3c0-0000-4000-8000-000000000001', 'name' => 'Основное меню'],
                ],
            ], 200);

        $connection = $this->connection();
        $response = app(IikoMenuService::class)->externalMenus($connection);

        $this->assertArrayHasKey('externalMenus', $response);

        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/api/2/menu')
                && $request['organizationIds'] === ['11111111-1111-4111-8111-111111111111'];
        });
    }

    public function test_external_menu_request_uses_current_v3_endpoint_and_optional_price_category(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['itemsGroups' => []], 200);

        $connection = $this->connection();
        $response = app(IikoMenuService::class)->externalMenu(
            $connection,
            'e4e0e3c0-0000-4000-8000-000000000001',
            '22222222-2222-4222-8222-222222222222'
        );

        $this->assertArrayHasKey('itemsGroups', $response);

        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/api/menu/v3/by_id')
                && $request['externalMenuId'] === 'e4e0e3c0-0000-4000-8000-000000000001'
                && $request['organizationId'] === '11111111-1111-4111-8111-111111111111'
                && $request['priceCategoryId'] === '22222222-2222-4222-8222-222222222222';
        });
    }

    private function connection(): IikoConnection
    {
        $connection = new IikoConnection([
            'organization_id' => 12,
            'api_key' => 'restaurant-api-key',
            'iiko_organization_id' => '11111111-1111-4111-8111-111111111111',
            'terminal_group_id' => '33333333-3333-4333-8333-333333333333',
        ]);

        $connection->id = 34;

        return $connection;
    }
}
