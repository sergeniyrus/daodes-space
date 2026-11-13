<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\PaymentWebhookEvent;
use Tests\Support\FakePaymentGateway;
use App\Models\Organization;
use App\Models\OrganizationOrderSetting;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeMembership;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class CustomerCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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


    public function test_manager_cook_and_courier_complete_delivery_order_and_manager_records_payment(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();

        $checkout = $this->openCheckout($customer, $organization, $item);

        $this->post(route('customer.checkout.store'), [
            'idempotency_key' => $checkout->viewData('idempotencyKey'),
            'customer_name' => 'Test customer',
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
            'payment_method' => Order::PAYMENT_CASH_ON_RECEIPT,
            'address_line' => 'Test street 10',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $manager = $this->createOrganizationManager($organization);
        $cook = $this->createOrganizationEmployee($organization, 'cook', '+436601234568');
        $courier = $this->createOrganizationEmployee($organization, 'courier', '+436601234569');

        $this->actingAs($manager)
            ->patch(route('organizations.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertRedirect(route('organizations.orders.index', $organization));

        foreach ([
            [$cook, 'cook'],
            [$courier, 'courier'],
        ] as [$employee, $role]) {
            $this->actingAs($manager)
                ->post(route('organizations.orders.assign', [$organization, $order]), [
                    'employee_id' => $employee->id,
                    'role' => $role,
                ])
                ->assertRedirect(route('organizations.orders.index', $organization));
        }

        $this->actingAs($cook, 'employee')
            ->patch(route('employee.organization.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_PREPARING,
            ])
            ->assertRedirect(route('employee.organization.orders.index', $organization));

        $this->actingAs($cook, 'employee')
            ->patch(route('employee.organization.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_READY,
            ])
            ->assertRedirect(route('employee.organization.orders.index', $organization));

        $this->actingAs($cook, 'employee')
            ->patch(route('employee.organization.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_HANDED_TO_COURIER,
            ])
            ->assertRedirect(route('employee.organization.orders.index', $organization));

        $this->actingAs($courier, 'employee')
            ->patch(route('employee.organization.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_IN_TRANSIT,
            ])
            ->assertRedirect(route('employee.organization.orders.index', $organization));

        $this->actingAs($courier, 'employee')
            ->patch(route('employee.organization.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_DELIVERED,
            ])
            ->assertRedirect(route('employee.organization.orders.index', $organization));

        $this->actingAs($manager)
            ->patch(route('organizations.orders.payment', [$organization, $order]))
            ->assertRedirect(route('organizations.orders.index', $organization));

        $this->actingAs($manager)
            ->patch(route('organizations.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_COMPLETED,
            ])
            ->assertRedirect(route('organizations.orders.index', $organization));

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_SUCCEEDED, $order->fresh()->payment_status);
        $this->assertSame(
            8,
            $order->statusHistory()->count(),
            'The initial status and every manager/employee transition must be recorded.'
        );

        $this->assertDatabaseHas('order_assignments', [
            'order_id' => $order->id,
            'employee_id' => $cook->id,
            'role' => 'cook',
        ]);

        $this->assertDatabaseHas('order_assignments', [
            'order_id' => $order->id,
            'employee_id' => $courier->id,
            'role' => 'courier',
        ]);

        $this->assertNotNull(
            $order->assignments()->where('role', 'cook')->firstOrFail()->unassigned_at
        );
        $this->assertNotNull(
            $order->assignments()->where('role', 'courier')->firstOrFail()->unassigned_at
        );

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => Order::PAYMENT_SUCCEEDED,
        ]);
    }


    public function test_manager_cannot_change_an_order_from_another_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$firstOrganization, $firstItem] = $this->createOrderableMenu('First cafe');
        [$secondOrganization, $secondItem] = $this->createOrderableMenu('Second cafe');
        $foreignOrder = $this->createPickupOrder($customer, $secondOrganization, $secondItem);
        $manager = $this->createOrganizationManager($firstOrganization);

        $this->actingAs($manager)
            ->patch(route('organizations.orders.status', [
                $firstOrganization,
                $foreignOrder,
            ]), [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertNotFound();

        $this->assertSame(Order::STATUS_NEW, $foreignOrder->fresh()->status);
    }

    public function test_employee_cannot_change_order_without_an_active_assignment(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);
        $cook = $this->createOrganizationEmployee(
            $organization,
            'cook',
            '+436601234568'
        );

        $this->actingAs($cook, 'employee')
            ->patch(route('employee.organization.orders.status', [
                $organization,
                $order,
            ]), [
                'status' => Order::STATUS_PREPARING,
            ])
            ->assertForbidden();

        $this->assertSame(Order::STATUS_NEW, $order->fresh()->status);
        $this->assertDatabaseCount('order_status_history', 1);
    }

    public function test_employee_cannot_change_order_from_another_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$firstOrganization] = $this->createOrderableMenu('First cafe');
        [$secondOrganization, $secondItem] = $this->createOrderableMenu('Second cafe');
        $foreignOrder = $this->createPickupOrder($customer, $secondOrganization, $secondItem);
        $cook = $this->createOrganizationEmployee(
            $firstOrganization,
            'cook',
            '+436601234568'
        );

        $this->actingAs($cook, 'employee')
            ->patch(route('employee.organization.orders.status', [
                $firstOrganization,
                $foreignOrder,
            ]), [
                'status' => Order::STATUS_PREPARING,
            ])
            ->assertNotFound();

        $this->assertSame(Order::STATUS_NEW, $foreignOrder->fresh()->status);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = $this->createVerifiedCustomer();
        $otherCustomer = Customer::query()->create([
            'name' => 'Other customer',
            'phone' => '+436601234568',
            'phone_verified_at' => now(),
        ]);
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($owner, $organization, $item);

        $this->actingAs($otherCustomer, 'customer')
            ->get(route('customer.orders.show', $order))
            ->assertNotFound();

        $this->assertSame($owner->id, $order->fresh()->customer_id);
    }

    public function test_new_order_notifies_only_active_managers_of_its_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('Notification cafe');
        [$otherOrganization] = $this->createOrderableMenu('Other notification cafe');

        $manager = $this->createOrganizationManager($organization);
        $otherManager = $this->createOrganizationManager($otherOrganization);

        $order = $this->createPickupOrder($customer, $organization, $item);

        $this->assertDatabaseHas('order_notifications', [
            'organization_id' => $organization->id,
            'order_id' => $order->id,
            'recipient_type' => 'user',
            'recipient_id' => $manager->id,
            'event_type' => 'new_order',
        ]);

        $this->assertDatabaseMissing('order_notifications', [
            'order_id' => $order->id,
            'recipient_type' => 'user',
            'recipient_id' => $otherManager->id,
        ]);

        $this->assertDatabaseCount('order_notifications', 1);
    }

    public function test_assignment_notifies_only_the_assigned_employee(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('Assignment cafe');
        [$otherOrganization] = $this->createOrderableMenu('Other assignment cafe');

        $order = $this->createPickupOrder($customer, $organization, $item);
        $manager = $this->createOrganizationManager($organization);
        $cook = $this->createOrganizationEmployee(
            $organization,
            'cook',
            '+436601234574'
        );
        $foreignCook = $this->createOrganizationEmployee(
            $otherOrganization,
            'cook',
            '+436601234575'
        );

        $this->actingAs($manager)
            ->post(route('organizations.orders.assign', [$organization, $order]), [
                'employee_id' => $cook->id,
                'role' => 'cook',
            ])
            ->assertRedirect();

        $notification = \App\Models\OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('recipient_type', 'employee')
            ->where('recipient_id', $cook->id)
            ->firstOrFail();

        $this->assertSame('order_assigned', $notification->event_type);

        $this->assertDatabaseMissing('order_notifications', [
            'order_id' => $order->id,
            'recipient_type' => 'employee',
            'recipient_id' => $foreignCook->id,
        ]);

        $this->actingAs($cook, 'employee')
            ->getJson(route('employee.organization.orders.notifications', $organization))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $notification->id);

        $this->actingAs($cook, 'employee')
            ->postJson(route('employee.organization.orders.notifications.read', [
                $organization,
                $notification,
            ]))
            ->assertOk()
            ->assertJson(['marked_read' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_manager_can_read_only_own_organization_order_notifications(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('Manager notification cafe');
        [$otherOrganization] = $this->createOrderableMenu('Foreign manager cafe');

        $manager = $this->createOrganizationManager($organization);
        $foreignManager = $this->createOrganizationManager($otherOrganization);
        $order = $this->createPickupOrder($customer, $organization, $item);

        $notification = \App\Models\OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('recipient_type', 'user')
            ->where('recipient_id', $manager->id)
            ->firstOrFail();

        $this->actingAs($manager)
            ->getJson(route('organizations.orders.notifications', $organization))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $notification->id);

        $this->actingAs($foreignManager)
            ->getJson(route('organizations.orders.notifications', $organization))
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson(route('organizations.orders.notifications.read', [
                $organization,
                $notification,
            ]))
            ->assertOk()
            ->assertJson(['marked_read' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_manager_status_change_notifies_only_the_order_customer(): void
    {
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = Customer::query()->create([
            'name' => 'Another customer',
            'phone' => '+436601234580',
            'phone_verified_at' => now(),
        ]);

        [$organization, $item] = $this->createOrderableMenu('Customer status notifications');
        $order = $this->createPickupOrder($customer, $organization, $item);
        $manager = $this->createOrganizationManager($organization);

        $this->actingAs($manager)
            ->patch(route('organizations.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertRedirect();

        $notification = \App\Models\OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->firstOrFail();

        $this->assertSame('order_status_changed', $notification->event_type);
        $this->assertStringContainsString('Подтверждён', $notification->message);

        $this->actingAs($customer, 'customer')
            ->getJson(route('customer.orders.notifications'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $notification->id)
            ->assertJsonPath('notifications.0.url', route('customer.orders.show', $order));

        $this->actingAs($otherCustomer, 'customer')
            ->getJson(route('customer.orders.notifications'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');

        $this->actingAs($otherCustomer, 'customer')
            ->postJson(route('customer.orders.notifications.read', $notification))
            ->assertNotFound();

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.orders.notifications.read', $notification))
            ->assertOk()
            ->assertJson(['marked_read' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_employee_status_change_notifies_the_order_customer(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('Employee status notifications');
        $order = $this->createPickupOrder($customer, $organization, $item);
        $order->update(['status' => Order::STATUS_CONFIRMED]);

        $cook = $this->createOrganizationEmployee(
            $organization,
            'cook',
            '+436601234581'
        );

        OrderAssignment::query()->create([
            'order_id' => $order->id,
            'employee_id' => $cook->id,
            'role' => 'cook',
            'assigned_at' => now(),
        ]);

        $this->actingAs($cook, 'employee')
            ->patch(route('employee.organization.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_PREPARING,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_notifications', [
            'organization_id' => $organization->id,
            'order_id' => $order->id,
            'recipient_type' => 'customer',
            'recipient_id' => $customer->id,
            'event_type' => 'order_status_changed',
        ]);
    }

    public function test_manager_cannot_assign_employee_from_another_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('First cafe');
        [$otherOrganization] = $this->createOrderableMenu('Second cafe');

        $order = $this->createPickupOrder($customer, $organization, $item);
        $manager = $this->createOrganizationManager($organization);
        $foreignCook = $this->createOrganizationEmployee(
            $otherOrganization,
            'cook',
            '+436601234572'
        );

        $this->actingAs($manager)
            ->post(route('organizations.orders.assign', [$organization, $order]), [
                'employee_id' => $foreignCook->id,
                'role' => 'cook',
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertDatabaseMissing('order_assignments', [
            'order_id' => $order->id,
            'employee_id' => $foreignCook->id,
            'role' => 'cook',
            'unassigned_at' => null,
        ]);
    }

    public function test_manager_cannot_assign_courier_to_pickup_order(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();

        $order = $this->createPickupOrder($customer, $organization, $item);
        $manager = $this->createOrganizationManager($organization);
        $courier = $this->createOrganizationEmployee(
            $organization,
            'courier',
            '+436601234573'
        );

        $this->actingAs($manager)
            ->post(route('organizations.orders.assign', [$organization, $order]), [
                'employee_id' => $courier->id,
                'role' => 'courier',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('order_assignments', [
            'order_id' => $order->id,
            'employee_id' => $courier->id,
            'role' => 'courier',
            'unassigned_at' => null,
        ]);
    }

    public function test_marking_an_offline_payment_paid_twice_is_idempotent(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);
        $manager = $this->createOrganizationManager($organization);

        $order->update(['status' => Order::STATUS_ISSUED]);

        $this->actingAs($manager)
            ->patch(route('organizations.orders.payment', [$organization, $order]))
            ->assertRedirect(route('organizations.orders.index', $organization));

        $this->actingAs($manager)
            ->patch(route('organizations.orders.payment', [$organization, $order]))
            ->assertRedirect(route('organizations.orders.index', $organization));

        $this->assertSame(Order::PAYMENT_SUCCEEDED, $order->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => Order::PAYMENT_SUCCEEDED,
        ]);
    }


    public function test_manager_cannot_mark_offline_payment_paid_before_customer_receives_order(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);
        $manager = $this->createOrganizationManager($organization);

        $this->actingAs($manager)
            ->patch(route('organizations.orders.payment', [$organization, $order]))
            ->assertSessionHasErrors('payment');

        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => Order::PAYMENT_PENDING,
        ]);
    }

    public function test_cancelling_unpaid_order_cancels_pending_payment_attempt(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);
        $manager = $this->createOrganizationManager($organization);

        $this->actingAs($manager)
            ->patch(route('organizations.orders.status', [$organization, $order]), [
                'status' => Order::STATUS_CANCELLED,
            ])
            ->assertRedirect(route('organizations.orders.index', $organization));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_CANCELLED, $order->fresh()->payment_status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => Order::PAYMENT_CANCELLED,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => Order::STATUS_NEW,
            'to_status' => Order::STATUS_CANCELLED,
            'actor_type' => 'user',
            'actor_id' => $manager->id,
        ]);
    }


    public function test_checkout_rejects_online_payment_until_a_gateway_is_configured(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $checkout = $this->openCheckout($customer, $organization, $item);

        $this->from(route('customer.checkout'))
            ->post(route('customer.checkout.store'), [
                'idempotency_key' => $checkout->viewData('idempotencyKey'),
                'customer_name' => 'Test customer',
                'fulfillment_type' => Order::FULFILLMENT_PICKUP,
                'payment_method' => Order::PAYMENT_ONLINE,
                'requested_at' => now()->addHour()->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect(route('customer.checkout'))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }


    public function test_signed_payment_webhook_marks_payment_paid_and_duplicate_event_is_idempotent(): void
    {
        [$order, $payment] = $this->createFakeOnlinePayment();
        $payload = json_encode([
            'event_id' => 'evt_paid_001',
            'payment_id' => 'provider_payment_001',
            'status' => Order::PAYMENT_SUCCEEDED,
            'amount' => '20.00',
            'currency' => 'EUR',
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        $first = $this->call('POST', '/api/webhooks/payments/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => $signature,
        ], $payload);

        $first->assertOk()->assertJson([
            'received' => true,
            'duplicate' => false,
            'processing_status' => 'processed',
        ]);
        $this->assertSame(Order::PAYMENT_SUCCEEDED, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_SUCCEEDED, $order->fresh()->payment_status);
        $this->assertNotNull($payment->fresh()->paid_at);

        $duplicate = $this->call('POST', '/api/webhooks/payments/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => $signature,
        ], $payload);

        $duplicate->assertOk()->assertJson([
            'received' => true,
            'duplicate' => true,
        ]);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertSame('processed', PaymentWebhookEvent::query()->firstOrFail()->processing_status);
    }

    public function test_payment_webhook_rejects_invalid_signature_without_changing_payment(): void
    {
        [$order, $payment] = $this->createFakeOnlinePayment();
        $payload = json_encode([
            'event_id' => 'evt_invalid_signature',
            'payment_id' => 'provider_payment_001',
            'status' => Order::PAYMENT_SUCCEEDED,
            'amount' => '20.00',
            'currency' => 'EUR',
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', '/api/webhooks/payments/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => 'invalid-signature',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame(Order::PAYMENT_PENDING, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_payment_webhook_rejects_amount_mismatch_and_keeps_payment_pending(): void
    {
        [$order, $payment] = $this->createFakeOnlinePayment();
        $payload = json_encode([
            'event_id' => 'evt_wrong_amount',
            'payment_id' => 'provider_payment_001',
            'status' => Order::PAYMENT_SUCCEEDED,
            'amount' => '21.00',
            'currency' => 'EUR',
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        $response = $this->call('POST', '/api/webhooks/payments/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => $signature,
        ], $payload);

        $response->assertOk()->assertJson([
            'received' => true,
            'processing_status' => 'rejected',
        ]);
        $this->assertSame(Order::PAYMENT_PENDING, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'fake',
            'event_id' => 'evt_wrong_amount',
            'processing_status' => 'rejected',
        ]);
    }

    public function test_payment_webhook_returns_service_unavailable_without_configured_provider(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'payments.providers' => [],
            'cache.default' => 'array',
        ]);

        $response = $this->call('POST', '/api/webhooks/payments/not-configured', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"event_id":"evt_unconfigured"}');

        $response->assertStatus(503);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }


    public function test_signed_refund_webhook_changes_succeeded_payment_to_refunded(): void
    {
        [$order, $payment] = $this->createFakeOnlinePayment();

        $sendEvent = function (string $eventId, string $status) {
            $payload = json_encode([
                'event_id' => $eventId,
                'payment_id' => 'provider_payment_001',
                'status' => $status,
                'amount' => '20.00',
                'currency' => 'EUR',
            ], JSON_THROW_ON_ERROR);

            return $this->call('POST', '/api/webhooks/payments/fake', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_FAKE_SIGNATURE' => hash_hmac(
                    'sha256',
                    $payload,
                    'test-webhook-secret'
                ),
            ], $payload);
        };

        $sendEvent('evt_paid_before_refund', Order::PAYMENT_SUCCEEDED)
            ->assertOk()
            ->assertJson(['processing_status' => 'processed']);

        $sendEvent('evt_refund_001', Order::PAYMENT_REFUNDED)
            ->assertOk()
            ->assertJson(['processing_status' => 'processed']);

        $this->assertSame(Order::PAYMENT_REFUNDED, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);
        $this->assertSame(Order::PAYMENT_REFUNDED, $order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_webhook_events', 2);
    }

    public function test_late_success_webhook_does_not_pay_an_already_cancelled_order(): void
    {
        [$order, $payment] = $this->createFakeOnlinePayment();
        $order->update(['status' => Order::STATUS_CANCELLED]);

        $payload = json_encode([
            'event_id' => 'evt_late_success',
            'payment_id' => 'provider_payment_001',
            'status' => Order::PAYMENT_SUCCEEDED,
            'amount' => '20.00',
            'currency' => 'EUR',
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', '/api/webhooks/payments/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => hash_hmac(
                'sha256',
                $payload,
                'test-webhook-secret'
            ),
        ], $payload);

        $response->assertOk()->assertJson([
            'received' => true,
            'processing_status' => 'rejected',
        ]);

        $this->assertSame(Order::PAYMENT_PENDING, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_late_success',
            'processing_status' => 'rejected',
        ]);
    }


    public function test_customer_can_poll_own_order_status_and_history(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);

        $order->update([
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $order->statusHistory()->create([
            'from_status' => Order::STATUS_NEW,
            'to_status' => Order::STATUS_CONFIRMED,
            'actor_type' => 'user',
            'actor_id' => null,
            'comment' => 'Заказ подтверждён менеджером.',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->getJson(route('customer.orders.updates', $order));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertJsonPath('status', Order::STATUS_CONFIRMED)
            ->assertJsonPath('payment_status', Order::PAYMENT_PENDING)
            ->assertJsonPath('history.1.status', Order::STATUS_CONFIRMED);
    }

    public function test_customer_cannot_poll_another_customers_order_updates(): void
    {
        $owner = $this->createVerifiedCustomer();
        $otherCustomer = Customer::query()->create([
            'name' => 'Another customer',
            'phone' => '+436601234568',
            'phone_verified_at' => now(),
        ]);

        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($owner, $organization, $item);

        $this->actingAs($otherCustomer, 'customer')
            ->getJson(route('customer.orders.updates', $order))
            ->assertNotFound();
    }

    public function test_cook_work_queue_renders_kds_lanes_and_preserves_order_actions(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);
        $order->update(['status' => Order::STATUS_CONFIRMED]);

        $cook = $this->createOrganizationEmployee(
            $organization,
            'cook',
            '+436601234572'
        );

        OrderAssignment::query()->create([
            'order_id' => $order->id,
            'employee_id' => $cook->id,
            'role' => 'cook',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($cook, 'employee')
            ->get(route('employee.organization.orders.updates', $organization));

        $response->assertOk()
            ->assertSee('kds-board')
            ->assertSee('К приготовлению')
            ->assertSee('Готовятся')
            ->assertSee('Готовы')
            ->assertSee('Заказ №' . $order->order_number)
            ->assertSee('Test dish')
            ->assertSee('× 2')
            ->assertSee('Начать готовить')
            ->assertSee('name="status" value="preparing"', false);
    }

    public function test_courier_work_queue_renders_mobile_delivery_details_and_actions(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);
        $order->update([
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
            'status' => Order::STATUS_HANDED_TO_COURIER,
            'delivery_address' => [
                'address_line' => 'Test street 15, Graz',
                'entrance' => '2',
                'floor' => '4',
                'apartment' => '18',
                'intercom' => '1818',
                'delivery_comment' => 'Позвонить у подъезда',
            ],
            'customer_comment' => 'Без лука',
        ]);

        $courier = $this->createOrganizationEmployee(
            $organization,
            'courier',
            '+436601234573'
        );

        OrderAssignment::query()->create([
            'order_id' => $order->id,
            'employee_id' => $courier->id,
            'role' => 'courier',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($courier, 'employee')
            ->get(route('employee.organization.orders.updates', $organization));

        $response->assertOk()
            ->assertSee('courier-board')
            ->assertSee('courier-card')
            ->assertSee('Заказ №' . $order->order_number)
            ->assertSee('Test street 15, Graz')
            ->assertSee('Подъезд 2')
            ->assertSee('этаж 4')
            ->assertSee('кв./офис 18')
            ->assertSee('Позвонить у подъезда')
            ->assertSee('Без лука')
            ->assertSee('Test customer')
            ->assertSee('tel:+436601234567')
            ->assertSee('Открыть маршрут')
            ->assertSee('Принять заказ и начать доставку')
            ->assertSee('name="status" value="in_transit"', false);
    }

    public function test_employee_order_updates_are_limited_to_their_active_role_assignments(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();

        $cookOrder = $this->createPickupOrder($customer, $organization, $item);
        $cookOrder->update([
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $courierOrder = $this->createPickupOrder($customer, $organization, $item);
        $courierOrder->update([
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
            'status' => Order::STATUS_HANDED_TO_COURIER,
            'delivery_address' => [
                'address_line' => 'Courier test address 10',
            ],
        ]);

        $unassignedOrder = $this->createPickupOrder($customer, $organization, $item);
        $unassignedOrder->update([
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $cook = $this->createOrganizationEmployee(
            $organization,
            'cook',
            '+436601234570'
        );
        $courier = $this->createOrganizationEmployee(
            $organization,
            'courier',
            '+436601234571'
        );

        OrderAssignment::query()->create([
            'order_id' => $cookOrder->id,
            'employee_id' => $cook->id,
            'role' => 'cook',
            'assigned_at' => now(),
        ]);

        OrderAssignment::query()->create([
            'order_id' => $courierOrder->id,
            'employee_id' => $courier->id,
            'role' => 'courier',
            'assigned_at' => now(),
        ]);

        $cookResponse = $this->actingAs($cook, 'employee')
            ->get(route('employee.organization.orders.updates', $organization));

        $cookResponse->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee($cookOrder->order_number)
            ->assertDontSee($courierOrder->order_number)
            ->assertDontSee($unassignedOrder->order_number);

        auth()->guard('employee')->logout();

        $courierResponse = $this->actingAs($courier, 'employee')
            ->get(route('employee.organization.orders.updates', $organization));

        $courierResponse->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee($courierOrder->order_number)
            ->assertDontSee($cookOrder->order_number)
            ->assertDontSee($unassignedOrder->order_number);
    }

    public function test_manager_courier_selector_prioritizes_the_least_loaded_active_courier(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();

        $firstOrder = $this->createPickupOrder($customer, $organization, $item);
        $secondOrder = $this->createPickupOrder($customer, $organization, $item);
        $thirdOrder = $this->createPickupOrder($customer, $organization, $item);

        foreach ([$firstOrder, $secondOrder, $thirdOrder] as $order) {
            $order->update([
                'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
                'status' => Order::STATUS_IN_TRANSIT,
            ]);
        }

        $busyCourier = $this->createOrganizationEmployee(
            $organization,
            'courier',
            '+436601234574'
        );
        $availableCourier = $this->createOrganizationEmployee(
            $organization,
            'courier',
            '+436601234575'
        );

        foreach ([$firstOrder, $secondOrder] as $order) {
            OrderAssignment::query()->create([
                'order_id' => $order->id,
                'employee_id' => $busyCourier->id,
                'role' => 'courier',
                'assigned_at' => now(),
            ]);
        }

        OrderAssignment::query()->create([
            'order_id' => $thirdOrder->id,
            'employee_id' => $busyCourier->id,
            'role' => 'courier',
            'assigned_at' => now(),
            'unassigned_at' => now(),
        ]);

        $manager = $this->createOrganizationManager($organization);

        $response = $this->actingAs($manager)
            ->get(route('organizations.orders.index', $organization));

        $response->assertOk()
            ->assertSee('0 в работе')
            ->assertSee('2 в работе');

        $html = preg_replace('/\\s+/', ' ', strip_tags($response->getContent()));
        $availablePosition = strpos($html, 'Courier test (0 в работе)');
        $busyPosition = strpos($html, 'Courier test (2 в работе)');

        $this->assertNotFalse($availablePosition);
        $this->assertNotFalse($busyPosition);
        $this->assertLessThan($busyPosition, $availablePosition);
    }

    public function test_manager_can_print_80mm_and_a4_receipts_only_for_their_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('Receipt cafe');
        [$otherOrganization, $otherItem] = $this->createOrderableMenu('Other receipt cafe');

        $order = $this->createPickupOrder($customer, $organization, $item);
        $otherOrder = $this->createPickupOrder($customer, $otherOrganization, $otherItem);
        $manager = $this->createOrganizationManager($organization);

        $baseUrl = route('organizations.orders.receipt', [
            'organization' => $organization->id,
            'order' => $order->id,
        ]);

        $this->actingAs($manager)
            ->get($baseUrl . '?format=80mm')
            ->assertOk()
            ->assertSee('data-receipt-format="80mm"', false)
            ->assertSee('ЧЕК №' . $order->order_number)
            ->assertSee('js/vendor/qrcode.min.js')
            ->assertSee(route('organizations.orders.show', [$organization, $order]));

        $this->actingAs($manager)
            ->get($baseUrl . '?format=a4')
            ->assertOk()
            ->assertSee('data-receipt-format="a4"', false)
            ->assertSee('ЧЕК №' . $order->order_number);

        $this->actingAs($manager)
            ->get(route('organizations.orders.receipt', [
                'organization' => $organization->id,
                'order' => $otherOrder->id,
                'format' => '80mm',
            ]))
            ->assertNotFound();
    }

    public function test_manager_can_view_order_details_only_for_their_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('Order details cafe');
        [$otherOrganization, $otherItem] = $this->createOrderableMenu('Other order details cafe');

        $order = $this->createPickupOrder($customer, $organization, $item);
        $otherOrder = $this->createPickupOrder($customer, $otherOrganization, $otherItem);

        DB::table('order_status_history')->insert([
            'order_id' => $order->id,
            'from_status' => Order::STATUS_NEW,
            'to_status' => Order::STATUS_CONFIRMED,
            'actor_type' => 'user',
            'actor_id' => null,
            'comment' => 'Заказ подтверждён менеджером.',
            'created_at' => now(),
        ]);

        $manager = $this->createOrganizationManager($organization);

        $this->actingAs($manager)
            ->get(route('organizations.orders.show', [$organization, $order]))
            ->assertOk()
            ->assertSee('Заказ №' . $order->order_number)
            ->assertSee('Состав заказа')
            ->assertSee('Оплата')
            ->assertSee('Назначенные сотрудники')
            ->assertSee('История заказа')
            ->assertSee('Заказ подтверждён менеджером.');

        $this->actingAs($manager)
            ->get(route('organizations.orders.show', [$organization, $otherOrder]))
            ->assertNotFound();
    }

    public function test_manager_order_history_filters_are_scoped_to_the_current_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('History cafe');
        [$otherOrganization, $otherItem] = $this->createOrderableMenu('Other history cafe');

        $matchingOrder = $this->createPickupOrder($customer, $organization, $item);
        $matchingOrder->update([
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_SUCCEEDED,
        ]);

        $nonMatchingOrder = $this->createPickupOrder($customer, $organization, $item);
        $nonMatchingOrder->update([
            'status' => Order::STATUS_NEW,
        ]);

        $otherOrder = $this->createPickupOrder($customer, $otherOrganization, $otherItem);
        $otherOrder->update([
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_SUCCEEDED,
        ]);

        $manager = $this->createOrganizationManager($organization);

        $response = $this->actingAs($manager)
            ->get(route('organizations.orders.index', [
                'organization' => $organization->id,
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_SUCCEEDED,
                'fulfillment_type' => Order::FULFILLMENT_PICKUP,
            ]));

        $response->assertOk()
            ->assertSee($matchingOrder->order_number)
            ->assertDontSee($nonMatchingOrder->order_number)
            ->assertDontSee($otherOrder->order_number)
            ->assertSee('Найдено заказов: 1');
    }

    public function test_manager_can_export_only_filtered_orders_from_their_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('CSV history cafe');
        [$otherOrganization, $otherItem] = $this->createOrderableMenu('Other CSV cafe');

        $includedOrder = $this->createPickupOrder($customer, $organization, $item);
        $includedOrder->update([
            'status' => Order::STATUS_COMPLETED,
        ]);

        $excludedOrder = $this->createPickupOrder($customer, $organization, $item);
        $excludedOrder->update([
            'status' => Order::STATUS_NEW,
        ]);

        $otherOrder = $this->createPickupOrder($customer, $otherOrganization, $otherItem);
        $otherOrder->update([
            'status' => Order::STATUS_COMPLETED,
        ]);

        $manager = $this->createOrganizationManager($organization);

        $response = $this->actingAs($manager)
            ->get(route('organizations.orders.export', [
                'organization' => $organization->id,
                'status' => Order::STATUS_COMPLETED,
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString((string) $includedOrder->order_number, $csv);
        $this->assertStringNotContainsString((string) $excludedOrder->order_number, $csv);
        $this->assertStringNotContainsString((string) $otherOrder->order_number, $csv);
    }

    public function test_organization_dashboard_shows_order_metrics_scoped_to_organization(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu('Dashboard cafe');
        [$otherOrganization, $otherItem] = $this->createOrderableMenu('Other cafe');

        $newOrder = $this->createPickupOrder($customer, $organization, $item);
        $kitchenOrder = $this->createPickupOrder($customer, $organization, $item);
        $readyOrder = $this->createPickupOrder($customer, $organization, $item);
        $deliveryOrder = $this->createPickupOrder($customer, $organization, $item);
        $completedOrder = $this->createPickupOrder($customer, $organization, $item);
        $cancelledOrder = $this->createPickupOrder($customer, $organization, $item);
        $otherOrder = $this->createPickupOrder($customer, $otherOrganization, $otherItem);

        $kitchenOrder->update(['status' => Order::STATUS_PREPARING]);
        $readyOrder->update(['status' => Order::STATUS_READY]);
        $deliveryOrder->update([
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
            'status' => Order::STATUS_IN_TRANSIT,
        ]);
        $completedOrder->update([
            'status' => Order::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $cancelledOrder->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        $otherOrder->update(['status' => Order::STATUS_NEW]);

        $courier = $this->createOrganizationEmployee(
            $organization,
            'courier',
            '+436601234576'
        );

        OrderAssignment::query()->create([
            'order_id' => $deliveryOrder->id,
            'employee_id' => $courier->id,
            'role' => 'courier',
            'assigned_at' => now(),
        ]);

        DB::table('payments')->insert([
            'order_id' => $completedOrder->id,
            'provider' => 'offline',
            'method' => Order::PAYMENT_CASH_ON_RECEIPT,
            'status' => Order::PAYMENT_SUCCEEDED,
            'amount' => '25.00',
            'currency' => 'EUR',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = $this->createOrganizationManager($organization);

        $response = $this->actingAs($manager)
            ->get(route('organizations.manage', $organization));

        $response->assertOk()
            ->assertSee('Заказов сегодня')
            ->assertSee('На кухне')
            ->assertSee('Готовы к выдаче')
            ->assertSee('У курьеров')
            ->assertSee('Завершено сегодня')
            ->assertSee('Отменено сегодня')
            ->assertSee('25,00 EUR')
            ->assertSee('Courier test')
            ->assertSee('1 в доставке');

        $response->assertSee('data-stat="orders-today"', false);
        $response->assertSee('data-stat="new-orders"', false);
        $response->assertSee('data-stat="delivery-orders"', false);
    }

    public function test_public_receipt_requires_a_valid_unexpired_token(): void
    {
        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);

        $this->withoutMiddleware(ThrottleRequests::class);

        $token = str_repeat('b', 64);
        $order->forceFill([
            'receipt_token_hash' => hash('sha256', $token),
            'receipt_token_expires_at' => now()->addDays(30),
        ])->save();

        $this->get(route('customer.receipt', ['token' => $token]))
            ->assertOk()
            ->assertSee('Электронный чек онлайн-заказа')
            ->assertSee($order->order_number)
            ->assertHeader('Cache-Control', 'private, no-store, max-age=0');

        $this->get(route('customer.receipt', ['token' => str_repeat('a', 64)]))
            ->assertNotFound();

        $order->forceFill([
            'receipt_token_expires_at' => now()->subSecond(),
        ])->save();

        $this->get(route('customer.receipt', ['token' => $token]))
            ->assertNotFound();
    }

    private function createFakeOnlinePayment(): array
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'payments.providers.fake' => FakePaymentGateway::class,
            'payments.testing_secret' => 'test-webhook-secret',
            'cache.default' => 'array',
        ]);

        $customer = $this->createVerifiedCustomer();
        [$organization, $item] = $this->createOrderableMenu();
        $order = $this->createPickupOrder($customer, $organization, $item);
        $payment = $order->payments()->firstOrFail();

        $payment->update([
            'provider' => 'fake',
            'provider_payment_id' => 'provider_payment_001',
            'method' => Order::PAYMENT_ONLINE,
        ]);
        $order->update(['payment_method' => Order::PAYMENT_ONLINE]);

        return [$order, $payment];
    }

    private function createOrganizationManager(Organization $organization): User
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Manager ' . $organization->id,
            'password' => Hash::make('test-password'),
            'keyword' => 'test-keyword',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_users')->insert([
            'organization_id' => $organization->id,
            'user_id' => $userId,
            'role' => 'manager',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createOrganizationEmployee(
        Organization $organization,
        string $role,
        string $phone
    ): OrganizationEmployee {
        $employee = OrganizationEmployee::query()->create([
            'name' => ucfirst($role) . ' test',
            'phone' => $phone,
            'password' => 'test-password',
            'phone_verified_at' => now(),
        ]);

        OrganizationEmployeeMembership::query()->create([
            'employee_id' => $employee->id,
            'organization_id' => $organization->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return $employee;
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


    private function createPickupOrder(
        Customer $customer,
        Organization $organization,
        MenuItem $item
    ): Order {
        $checkout = $this->openCheckout($customer, $organization, $item);

        $this->post(route('customer.checkout.store'), [
            'idempotency_key' => $checkout->viewData('idempotencyKey'),
            'customer_name' => 'Test customer',
            'fulfillment_type' => Order::FULFILLMENT_PICKUP,
            'payment_method' => Order::PAYMENT_CASH_ON_RECEIPT,
            'requested_at' => now()->addHour()->format('Y-m-d\\TH:i'),
        ])->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
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
