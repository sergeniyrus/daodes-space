<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationOrderSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLegacyOrganizationSchema();
    }

    public function test_verified_customer_can_create_delivery_order_with_server_calculated_totals_and_snapshots(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();

        $checkout = $this->openCheckout($customer, $organization, $item);
        $idempotencyKey = $checkout->viewData('idempotencyKey');

        $response = $this->post(route('customer.checkout.store'), [
            'idempotency_key' => $idempotencyKey,
            'customer_name' => 'Test customer',
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
            'payment_method' => Order::PAYMENT_CASH_ON_RECEIPT,
            'address_line' => 'Test street 10',
            'entrance' => '2',
            'floor' => '3',
            'apartment' => '15',
            'delivery_comment' => 'Call on arrival',
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('customer.orders.show', $order));

        $this->assertSame('20.00', $order->subtotal);
        $this->assertSame('5.00', $order->delivery_fee);
        $this->assertSame('25.00', $order->total);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame(Order::STATUS_NEW, $order->status);
        $this->assertSame('Test street 10', $order->delivery_address['address_line']);
        $this->assertSame('Call on arrival', $order->delivery_address['delivery_comment']);

        $orderItem = $order->items()->firstOrFail();

        $this->assertSame('Test dish', $orderItem->item_name);
        $this->assertSame('10.00', $orderItem->unit_price);
        $this->assertSame(2, $orderItem->quantity);
        $this->assertSame('20.00', $orderItem->line_total);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'offline',
            'method' => Order::PAYMENT_CASH_ON_RECEIPT,
            'status' => Order::PAYMENT_PENDING,
            'amount' => '25.00',
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => Order::STATUS_NEW,
            'actor_type' => 'customer',
            'actor_id' => $customer->id,
        ]);

        $item->update([
            'name' => 'Renamed dish',
            'price' => '99.00',
        ]);

        $this->assertSame('Test dish', $orderItem->fresh()->item_name);
        $this->assertSame('10.00', $orderItem->fresh()->unit_price);
        $this->assertSame('99.00', $item->fresh()->price);
    }

    public function test_repeating_checkout_post_with_same_idempotency_key_does_not_create_a_second_order(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();

        $checkout = $this->openCheckout($customer, $organization, $item);
        $payload = [
            'idempotency_key' => $checkout->viewData('idempotencyKey'),
            'customer_name' => 'Test customer',
            'fulfillment_type' => Order::FULFILLMENT_PICKUP,
            'payment_method' => Order::PAYMENT_CASH_ON_RECEIPT,
            'requested_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ];

        $firstResponse = $this->post(route('customer.checkout.store'), $payload);
        $order = Order::query()->firstOrFail();

        $firstResponse->assertRedirect(route('customer.orders.show', $order));

        $secondResponse = $this->post(route('customer.checkout.store'), $payload);

        $secondResponse->assertRedirect(route('customer.orders.show', $order));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_status_history', 1);
    }

    public function test_checkout_rejects_a_menu_price_changed_after_checkout_page_was_opened(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();

        $checkout = $this->openCheckout($customer, $organization, $item);

        $item->update(['price' => '11.00']);

        $response = $this->from(route('customer.checkout'))
            ->post(route('customer.checkout.store'), [
                'idempotency_key' => $checkout->viewData('idempotencyKey'),
                'customer_name' => 'Test customer',
                'fulfillment_type' => Order::FULFILLMENT_PICKUP,
                'payment_method' => Order::PAYMENT_CASH_ON_RECEIPT,
                'requested_at' => now()->addHour()->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect(route('customer.checkout'));
        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_does_not_accept_a_menu_item_from_another_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization] = $this->createOrderableMenu('First organization');
        [$otherOrganization, $foreignItem] = $this->createOrderableMenu('Other organization');

        $this->actingAs($customer, 'customer')->withSession([
            'customer_order_cart' => [
                'organization_id' => $organization->id,
                'items' => [
                    $foreignItem->id => 1,
                ],
            ],
        ]);

        $response = $this->get(route('customer.checkout'));

        $response->assertRedirect(route('customer.cart'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertNotSame($organization->id, $otherOrganization->id);
    }

    private function createVerifiedCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Test customer',
            'phone' => '+436601234567',
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * @return array{0: Organization, 1: MenuItem}
     */
    private function createOrderableMenu(string $organizationName = 'Test cafe'): array
    {
        $organization = Organization::query()->create([
            'name' => $organizationName,
            'status' => 'active',
        ]);

        OrganizationOrderSetting::query()->create([
            'organization_id' => $organization->id,
            'currency' => 'EUR',
            'delivery_fee' => '5.00',
            'minimum_order' => '10.00',
            'orders_enabled' => true,
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'dine_in_enabled' => true,
            'cash_payment_enabled' => true,
            'card_payment_enabled' => true,
            'online_payment_enabled' => false,
            'pickup_min_minutes' => null,
        ]);

        $section = MenuSection::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Main menu',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $item = MenuItem::query()->create([
            'menu_section_id' => $section->id,
            'name' => 'Test dish',
            'weight' => 250,
            'unit' => 'g',
            'price' => '10.00',
            'description' => 'Test description',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return [$organization, $item];
    }

    private function openCheckout(
        Customer $customer,
        Organization $organization,
        MenuItem $item
    ) {
        return $this->actingAs($customer, 'customer')
            ->withSession([
                'customer_order_cart' => [
                    'organization_id' => $organization->id,
                    'items' => [
                        $item->id => 2,
                    ],
                ],
            ])
            ->get(route('customer.checkout'))
            ->assertOk();
    }

    /**
     * Test-only representation of the existing legacy schema.
     *
     * These tables are not production migrations and are created only after
     * RefreshDatabase has migrated the application's own tables.
     */
    private function createLegacyOrganizationSchema(): void
    {
        if (!Schema::hasTable('organization_types')) {
            Schema::create('organization_types', function (Blueprint $table) {
                $table->id();
            });
        }

        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->unsignedBigInteger('type_id')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index('status', 'idx_organizations_status');
                $table->index('type_id', 'organizations_type_id_index');
                $table->foreign('type_id')
                    ->references('id')
                    ->on('organization_types')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('organization_users')) {
            Schema::create('organization_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 50)->default('manager');
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(
                    ['organization_id', 'user_id'],
                    'uq_organization_user'
                );
                $table->index('user_id', 'idx_organization_users_user_id');
                $table->index('organization_id', 'idx_organization_users_organization_id');
                $table->index('role', 'idx_organization_users_role');

                $table->foreign('organization_id', 'fk_organization_users_organization')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();

                $table->foreign('user_id', 'fk_organization_users_user')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('menu_sections')) {
            Schema::create('menu_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->string('name', 255);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(
                    ['organization_id', 'name'],
                    'uq_section_org_name'
                );
                $table->index('organization_id', 'menu_sections_organization_id_index');
                $table->index(
                    ['organization_id', 'sort_order'],
                    'menu_sections_sort_order_index'
                );

                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('menu_section_id');
                $table->string('name', 255);
                $table->unsignedInteger('weight')->nullable();
                $table->string('unit', 20)->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(
                    ['menu_section_id', 'name', 'weight', 'price'],
                    'uq_menu_item_section_name_weight_price'
                );
                $table->index('menu_section_id', 'menu_items_menu_section_id_index');
                $table->index(
                    ['menu_section_id', 'sort_order'],
                    'menu_items_sort_order_index'
                );

                $table->foreign('menu_section_id')
                    ->references('id')
                    ->on('menu_sections')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }
}
