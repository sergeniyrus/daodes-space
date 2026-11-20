<?php

namespace Tests\Unit;

use App\Models\IikoConnection;
use App\Services\Iiko\IikoAnalyticsService;
use App\Services\Iiko\IikoInventoryService;
use App\Services\Iiko\IikoFinanceService;
use App\Services\Iiko\IikoLoyaltyService;
use App\Services\Iiko\IikoReservationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IikoAdvancedServiceTest extends TestCase
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

    public function test_loyalty_customer_lookup_uses_a_synthetic_card_number(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['id' => '44444444-4444-4444-8444-444444444444', 'wallets' => []], 200);

        $result = app(IikoLoyaltyService::class)->customerInfo(
            $this->connection(),
            'cardNumber',
            'TEST-CARD-0001'
        );

        $this->assertSame(
            '44444444-4444-4444-8444-444444444444',
            $result['id']
        );

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/1/loyalty/iiko/customer/info')
            && $request['type'] === 'cardNumber'
            && $request['cardNumber'] === 'TEST-CARD-0001'
        );
    }

    public function test_reservation_create_uses_selected_organization_and_terminal(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['reserveInfo' => ['id' => '55555555-5555-4555-8555-555555555555']], 200);

        $result = app(IikoReservationService::class)->create($this->connection(), [
            'customer' => ['type' => 'regular', 'name' => 'Test Guest'],
            'phone' => '+10000000000',
            'guestsCount' => 2,
            'durationInMinutes' => 90,
            'shouldRemind' => true,
            'tableIds' => ['66666666-6666-4666-8666-666666666666'],
            'estimatedStartTime' => '2026-12-01 19:00:00.000',
            'comment' => 'Synthetic reservation test',
        ]);

        $this->assertArrayHasKey('reserveInfo', $result);

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/1/reserve/create')
            && $request['organizationId'] === '11111111-1111-4111-8111-111111111111'
            && $request['terminalGroupId'] === '33333333-3333-4333-8333-333333333333'
        );
    }

    public function test_analytics_service_requests_sales_report_columns(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['columns' => []], 200);

        $result = app(IikoAnalyticsService::class)->columns($this->connection(), 'SALES');

        $this->assertArrayHasKey('columns', $result);

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/reporting/v1/olap/columns/get')
            && $request['reportType'] === 'SALES'
        );
    }

    public function test_inventory_service_requests_stock_balances_for_selected_organization(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['items' => []], 200);

        $result = app(IikoInventoryService::class)->stockBalances(
            $this->connection(),
            '2026-12-01T00:00:00',
            ['77777777-7777-4777-8777-777777777777']
        );

        $this->assertArrayHasKey('items', $result);

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/inventory/v1/stock_balance/list')
            && $request['organizationId'] === '11111111-1111-4111-8111-111111111111'
            && $request['storeIds'] === ['77777777-7777-4777-8777-777777777777']
        );
    }


    public function test_finance_service_requests_accounts_without_mutating_financial_data(): void
    {
        Http::fakeSequence()
            ->push(['token' => 'test-jwt'], 200)
            ->push(['accounts' => []], 200);

        $result = app(IikoFinanceService::class)->accounts($this->connection());

        $this->assertArrayHasKey('accounts', $result);

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/finance/v1/account/list')
            && $request['filters'] === []
            && $request['limit'] === 100
            && $request['offset'] === 0
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
