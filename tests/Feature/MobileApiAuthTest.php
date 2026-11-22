<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MobileApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        RateLimiter::shouldReceive('tooManyAttempts')->andReturnFalse();
        RateLimiter::shouldReceive('hit');
        RateLimiter::shouldReceive('clear');

        $this->createPublicCatalogSchema();
    }

    public function test_mobile_health_endpoint_is_public(): void
    {
        $this->getJson('/api/mobile/v1/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'application' => 'DAODES',
                'api_version' => 'v1',
            ]);
    }

    public function test_verified_customer_can_sign_in_and_receive_a_mobile_token(): void
    {
        $customer = $this->createCustomer();

        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'phone' => '+43 660 123 45 67',
            'password' => 'Correct-password-123',
            'device_name' => 'DAODES Android test',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.phone', '+436601234567')
            ->assertJsonStructure(['access_token', 'expires_at']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Customer::class,
            'tokenable_id' => $customer->id,
            'name' => 'DAODES Android test',
        ]);
    }

    public function test_mobile_token_can_read_customer_profile(): void
    {
        $customer = $this->createCustomer();
        $createdToken = $customer->createToken('DAODES Mobile test', ['mobile']);
        $customer->withAccessToken($createdToken->accessToken);

        $resolvedToken = PersonalAccessToken::findToken($createdToken->plainTextToken);

        $this->assertNotNull($resolvedToken);
        $this->assertInstanceOf(Customer::class, $resolvedToken->tokenable);

        $this->withToken($createdToken->plainTextToken)
            ->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonMissingPath('customer.password');
    }

    public function test_mobile_logout_revokes_the_current_device_token(): void
    {
        $customer = $this->createCustomer();
        $createdToken = $customer->createToken('DAODES Mobile test', ['mobile']);
        $customer->withAccessToken($createdToken->accessToken);

        $this->withToken($createdToken->plainTextToken)
            ->postJson('/api/mobile/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_login_rejects_an_incorrect_password(): void
    {
        $customer = $this->createCustomer();

        $this->postJson('/api/mobile/v1/auth/login', [
            'phone' => $customer->phone,
            'password' => 'Wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Неверный номер телефона или пароль.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_login_rejects_an_unverified_phone(): void
    {
        $customer = $this->createCustomer(verified: false);

        $this->postJson('/api/mobile/v1/auth/login', [
            'phone' => $customer->phone,
            'password' => 'Correct-password-123',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Сначала подтвердите номер телефона через SMS и установите пароль.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_organizations_endpoint_returns_a_public_data_collection(): void
    {
        $this->getJson('/api/mobile/v1/organizations')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    private function createPublicCatalogSchema(): void
    {
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('menu_sections')) {
            Schema::create('menu_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->boolean('delivery_enabled')->default(true);
                $table->boolean('pickup_enabled')->default(true);
                $table->boolean('dine_in_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('menu_section_id');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->boolean('delivery_enabled')->default(true);
                $table->boolean('pickup_enabled')->default(true);
                $table->boolean('dine_in_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('organization_order_settings')) {
            Schema::create('organization_order_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->timestamps();
            });
        }
    }

    private function createCustomer(bool $verified = true): Customer
    {
        return Customer::query()->create([
            'name' => 'Mobile API Test',
            'phone' => '+436601234567',
            'password' => Hash::make('Correct-password-123'),
            'phone_verified_at' => $verified ? now() : null,
        ]);
    }
}
