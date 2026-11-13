<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderTransitionPolicy;
use PHPUnit\Framework\TestCase;

class OrderTransitionPolicyTest extends TestCase
{
    public function test_manager_can_confirm_only_new_orders(): void
    {
        $this->assertNull(OrderTransitionPolicy::managerError(
            Order::STATUS_NEW,
            Order::STATUS_CONFIRMED,
            Order::FULFILLMENT_PICKUP,
            Order::PAYMENT_PENDING
        ));

        $this->assertNotNull(OrderTransitionPolicy::managerError(
            Order::STATUS_PREPARING,
            Order::STATUS_CONFIRMED,
            Order::FULFILLMENT_PICKUP,
            Order::PAYMENT_PENDING
        ));
    }

    public function test_manager_can_issue_only_ready_pickup_orders(): void
    {
        $this->assertNull(OrderTransitionPolicy::managerError(
            Order::STATUS_READY,
            Order::STATUS_ISSUED,
            Order::FULFILLMENT_PICKUP,
            Order::PAYMENT_PENDING
        ));

        $this->assertNotNull(OrderTransitionPolicy::managerError(
            Order::STATUS_READY,
            Order::STATUS_ISSUED,
            Order::FULFILLMENT_DELIVERY,
            Order::PAYMENT_PENDING
        ));
    }

    public function test_manager_cannot_complete_an_unpaid_order(): void
    {
        $this->assertNotNull(OrderTransitionPolicy::managerError(
            Order::STATUS_DELIVERED,
            Order::STATUS_COMPLETED,
            Order::FULFILLMENT_DELIVERY,
            Order::PAYMENT_PENDING
        ));

        $this->assertNull(OrderTransitionPolicy::managerError(
            Order::STATUS_DELIVERED,
            Order::STATUS_COMPLETED,
            Order::FULFILLMENT_DELIVERY,
            Order::PAYMENT_SUCCEEDED
        ));
    }

    public function test_manager_cannot_cancel_a_paid_order(): void
    {
        $this->assertNotNull(OrderTransitionPolicy::managerError(
            Order::STATUS_CONFIRMED,
            Order::STATUS_CANCELLED,
            Order::FULFILLMENT_PICKUP,
            Order::PAYMENT_SUCCEEDED
        ));

        $this->assertNull(OrderTransitionPolicy::managerError(
            Order::STATUS_CONFIRMED,
            Order::STATUS_CANCELLED,
            Order::FULFILLMENT_PICKUP,
            Order::PAYMENT_PENDING
        ));
    }

    public function test_cook_can_handoff_delivery_only_when_courier_is_assigned(): void
    {
        $this->assertNotNull(OrderTransitionPolicy::employeeError(
            'cook',
            Order::STATUS_READY,
            Order::STATUS_HANDED_TO_COURIER,
            Order::FULFILLMENT_DELIVERY,
            false
        ));

        $this->assertNull(OrderTransitionPolicy::employeeError(
            'cook',
            Order::STATUS_READY,
            Order::STATUS_HANDED_TO_COURIER,
            Order::FULFILLMENT_DELIVERY,
            true
        ));
    }

    public function test_courier_can_only_accept_and_deliver_handed_over_orders(): void
    {
        $this->assertNull(OrderTransitionPolicy::employeeError(
            'courier',
            Order::STATUS_HANDED_TO_COURIER,
            Order::STATUS_IN_TRANSIT,
            Order::FULFILLMENT_DELIVERY,
            false
        ));

        $this->assertNull(OrderTransitionPolicy::employeeError(
            'courier',
            Order::STATUS_IN_TRANSIT,
            Order::STATUS_DELIVERED,
            Order::FULFILLMENT_DELIVERY,
            false
        ));

        $this->assertNotNull(OrderTransitionPolicy::employeeError(
            'courier',
            Order::STATUS_READY,
            Order::STATUS_DELIVERED,
            Order::FULFILLMENT_DELIVERY,
            false
        ));
    }

    public function test_courier_can_close_only_delivered_orders_after_successful_payment(): void
    {
        $this->assertNull(OrderTransitionPolicy::employeeError(
            'courier',
            Order::STATUS_DELIVERED,
            Order::STATUS_COMPLETED,
            Order::FULFILLMENT_DELIVERY,
            false,
            Order::PAYMENT_SUCCEEDED
        ));

        $this->assertNotNull(OrderTransitionPolicy::employeeError(
            'courier',
            Order::STATUS_DELIVERED,
            Order::STATUS_COMPLETED,
            Order::FULFILLMENT_DELIVERY,
            false,
            Order::PAYMENT_PENDING
        ));
    }

    public function test_employee_without_cook_or_courier_role_cannot_change_orders(): void
    {
        $this->assertNotNull(OrderTransitionPolicy::employeeError(
            'employee',
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::FULFILLMENT_PICKUP,
            false
        ));
    }
}
