<?php

namespace Tests\Unit;

use App\Models\IikoConnection;
use App\Services\Iiko\IikoPaymentService;
use App\Services\Iiko\IikoTableService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IikoReferenceDataServiceTest extends TestCase
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

    public function test_payment_types_are_requested_for_the_selected_organization(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['paymentTypes' => []], 200);

        $connection = $this->connection();
        $result = app(IikoPaymentService::class)->paymentTypes($connection);

        $this->assertArrayHasKey('paymentTypes', $result);

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/1/payment_types')
            && $request['organizationIds'] === ['11111111-1111-4111-8111-111111111111']
        );
    }

    public function test_restaurant_sections_are_requested_for_the_selected_terminal_group(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['restaurantSections' => []], 200);

        $connection = $this->connection();
        $result = app(IikoTableService::class)->restaurantSections($connection);

        $this->assertArrayHasKey('restaurantSections', $result);

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/1/reserve/available_restaurant_sections')
            && $request['terminalGroupIds'] === ['33333333-3333-4333-8333-333333333333']
        );
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
