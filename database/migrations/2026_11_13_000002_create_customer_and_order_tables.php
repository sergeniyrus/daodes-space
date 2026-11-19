<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->string('phone', 20)->unique();
            $table->timestamp('phone_verified_at')->nullable();

            // Связь с DAODES users появляется только после полной регистрации.
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
        }

        if (!Schema::hasTable('customer_organizations')) {
        Schema::create('customer_organizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Источник связи хранится на отношении customer ↔ organization.
            $table->string('source', 32)->default('order');
            $table->timestamps();

            $table->unique(
                ['customer_id', 'organization_id'],
                'customer_organization_unique'
            );

            $table->index(
                ['organization_id', 'created_at'],
                'customer_org_created_index'
            );
        });
        }

        if (!Schema::hasTable('customer_phone_challenges')) {
        Schema::create('customer_phone_challenges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // HMAC-SHA256. Открытый OTP в базе не хранится.
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['customer_id', 'created_at'],
                'customer_phone_rate_index'
            );

            $table->index(
                ['customer_id', 'id'],
                'customer_phone_latest_index'
            );
        });
        }

        if (!Schema::hasTable('customer_addresses')) {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('label', 50)->nullable();
            $table->string('recipient_name', 100)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('address_line', 500);
            $table->string('entrance', 30)->nullable();
            $table->string('floor', 30)->nullable();
            $table->string('apartment', 30)->nullable();
            $table->string('intercom', 50)->nullable();
            $table->text('delivery_comment')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(
                ['customer_id', 'is_default'],
                'customer_address_default_index'
            );
        });
        }

        if (!Schema::hasTable('orders')) {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->string('order_number', 32);
            $table->string('idempotency_key', 64)->nullable();

            // delivery | pickup | dine_in
            $table->string('fulfillment_type', 20);

            // new | awaiting_payment | confirmed | preparing | ready |
            // handed_to_courier | in_transit | issued | served |
            // delivered | completed | cancelled
            $table->string('status', 32)->default('new');

            // online | cash_on_receipt | card_on_receipt
            $table->string('payment_method', 24);

            // pending | succeeded | failed | cancelled | refunded
            $table->string('payment_status', 24)->default('pending');

            // Контактные данные и адрес — неизменяемый снимок на момент заказа.
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);
            $table->text('customer_comment')->nullable();
            $table->json('delivery_address')->nullable();
            $table->string('table_number', 30)->nullable();
            $table->timestamp('requested_at')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->char('currency', 3);

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'order_number'],
                'organization_order_number_unique'
            );

            $table->unique(
                ['organization_id', 'idempotency_key'],
                'organization_order_idempotency_unique'
            );

            $table->index(
                ['organization_id', 'status', 'created_at'],
                'organization_orders_status_created_index'
            );

            $table->index(
                ['customer_id', 'created_at'],
                'customer_orders_created_index'
            );
        });
        }

        if (!Schema::hasTable('order_items')) {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('menu_item_id')
                ->nullable()
                ->constrained('menu_items')
                ->nullOnDelete();

            // Снимок названия, описания, веса и цены — изменения меню
            // не должны менять уже оформленный заказ.
            $table->string('item_name', 255);
            $table->text('item_description')->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'id'], 'order_items_order_index');
        });
        }

        if (!Schema::hasTable('order_status_history')) {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);

            // system | customer | user | employee
            $table->string('actor_type', 24)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['order_id', 'created_at'],
                'order_status_history_order_created_index'
            );
        });
        }

        if (!Schema::hasTable('order_assignments')) {
        Schema::create('order_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('organization_employees')
                ->nullOnDelete();

            // cook | courier | employee
            $table->string('role', 20);

            $table->foreignId('assigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(
                ['order_id', 'role', 'unassigned_at'],
                'order_assignments_order_role_index'
            );

            $table->index(
                ['employee_id', 'role', 'unassigned_at'],
                'order_assignments_employee_role_index'
            );
        });
        }

        if (!Schema::hasTable('payments')) {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('provider', 64);
            $table->string('provider_payment_id', 128)->nullable();
            $table->string('method', 24);
            $table->string('status', 24)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('idempotency_key', 64)->nullable();

            // Только очищенные технические метаданные, не карточные данные.
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_payment_id'],
                'payment_provider_reference_unique'
            );

            $table->unique(
                ['order_id', 'idempotency_key'],
                'order_payment_idempotency_unique'
            );

            $table->index(
                ['order_id', 'status'],
                'payments_order_status_index'
            );
        });
        }
    }

    public function down(): void
    {
        // Не удаляем таблицы и данные: они могли существовать до запуска миграции.
    }
};
