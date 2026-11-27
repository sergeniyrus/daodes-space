<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnlineOrdersSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_order_tables_are_created_by_migrations(): void
    {
        $tables = [
            'customers',
            'customer_organizations',
            'customer_phone_challenges',
            'customer_addresses',
            'customer_carts',
            'orders',
            'order_items',
            'order_status_history',
            'order_assignments',
            'payments',
            'organization_order_settings',
            'organization_employees',
            'organization_employee_memberships',
            'employee_phone_challenges',
            'customer_devices',
            'push_notifications',
            'push_notification_deliveries',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected database table [{$table}] to exist after migrations."
            );
        }
    }

    public function test_order_tables_keep_the_required_snapshots_and_payment_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('orders', [
            'organization_id',
            'customer_id',
            'idempotency_key',
            'fulfillment_type',
            'status',
            'payment_method',
            'payment_status',
            'customer_name',
            'customer_phone',
            'delivery_address',
            'subtotal',
            'delivery_fee',
            'total',
            'currency',
        ]));

        $this->assertTrue(Schema::hasColumns('order_items', [
            'order_id',
            'menu_item_id',
            'item_name',
            'item_description',
            'weight',
            'unit',
            'unit_price',
            'quantity',
            'line_total',
            'options',
        ]));

        $this->assertTrue(Schema::hasColumns('payments', [
            'order_id',
            'provider',
            'provider_payment_id',
            'method',
            'status',
            'amount',
            'currency',
            'idempotency_key',
            'metadata',
            'paid_at',
            'failed_at',
            'refunded_at',
        ]));
    }

    public function test_ordering_schedule_and_guest_card_fields_are_migrated(): void
    {
        $this->assertTrue(Schema::hasColumns('organization_order_settings', [
            'working_hours',
            'timezone',
            'preorders_enabled',
            'order_lead_minutes',
            'time_slot_minutes',
        ]));

        $this->assertTrue(Schema::hasColumn('orders', 'guest_card_identifier'));
        $this->assertTrue(Schema::hasColumn('orders', 'pickup_address_snapshot'));
    }

    public function test_customer_otp_and_order_history_are_persisted_separately(): void
    {
        $this->assertTrue(Schema::hasColumns('customers', [
            'phone',
            'phone_verified_at',
            'user_id',
        ]));

        $this->assertTrue(Schema::hasColumns('customer_carts', [
            'customer_id',
            'organization_id',
            'items',
        ]));

        $this->assertTrue(Schema::hasColumns('customer_phone_challenges', [
            'customer_id',
            'code_hash',
            'attempts',
            'expires_at',
            'consumed_at',
            'revoked_at',
        ]));

        $this->assertTrue(Schema::hasColumns('order_status_history', [
            'order_id',
            'from_status',
            'to_status',
            'actor_type',
            'actor_id',
            'comment',
            'created_at',
        ]));
    }
}
