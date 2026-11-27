<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class MobileCommerceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->createPublicCatalogSchema();
    }

    public function test_mobile_customer_can_load_empty_cart_and_order_history(): void
    {
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/mobile/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.can_order', false);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/mobile/v1/orders')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_mobile_customer_can_manage_only_their_delivery_addresses(): void
    {
        $customer = $this->verifiedCustomer();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/mobile/v1/addresses', [
                'label' => 'Дом',
                'address_line' => 'Тестовая улица',
                'house_number' => '12',
                'latitude' => 55.75,
                'longitude' => 37.61,
                'is_favorite' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Дом')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.is_favorite', true);

        $addressId = $response->json('data.id');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/mobile/v1/addresses')
            ->assertOk()
            ->assertJsonPath('data.0.id', $addressId);

        $otherCustomer = $this->verifiedCustomer('+436601234568');

        $this->actingAs($otherCustomer, 'sanctum')
            ->deleteJson('/api/mobile/v1/addresses/' . $addressId)
            ->assertNotFound();

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addressId,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_mobile_checkout_requires_a_persisted_customer_cart(): void
    {
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/mobile/v1/checkout')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');
    }

    public function test_mobile_customer_can_load_and_mark_notifications_read(): void
    {
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/mobile/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/mobile/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_mobile_push_device_registration_requires_firebase_configuration(): void
    {
        $customer = $this->verifiedCustomer();

        config()->set('services.firebase.project_id', null);
        config()->set('services.firebase.client_email', null);
        config()->set('services.firebase.private_key', null);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/mobile/v1/push/devices', [
                'token' => 'mobile-fcm-token',
                'platform' => 'android',
                'device_name' => 'DAODES Android',
            ])
            ->assertServiceUnavailable();

        $this->assertDatabaseCount('customer_devices', 0);
    }

    private function verifiedCustomer(string $phone = '+436601234567'): Customer
    {
        return Customer::query()->create([
            'name' => 'Mobile commerce test',
            'phone' => $phone,
            'password' => Hash::make('Correct-password-123'),
            'phone_verified_at' => now(),
        ]);
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
                $table->boolean('is_available')->default(true);
                $table->string('unavailable_reason', 255)->nullable();
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
}
