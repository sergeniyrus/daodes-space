<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_devices')) {
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('platform', 16);
            $table->string('device_name', 120)->nullable();
            $table->text('push_token');
            $table->char('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'is_active'], 'customer_devices_active_index');
        });
        }
        if (!Schema::hasTable('push_notifications')) {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('event_key', 191)->unique();
            $table->string('event_type', 50);
            $table->string('title', 180);
            $table->string('body', 500);
            $table->json('data')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at'], 'push_notifications_customer_created_index');
            $table->index(['status', 'created_at'], 'push_notifications_status_created_index');
        });
        }
        if (!Schema::hasTable('push_notification_deliveries')) {
        Schema::create('push_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_notification_id')->constrained('push_notifications')->cascadeOnDelete();
            $table->foreignId('customer_device_id')->constrained('customer_devices')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['push_notification_id', 'customer_device_id'], 'push_notification_device_unique');
            $table->index(['status', 'updated_at'], 'push_deliveries_status_updated_index');
        });
        }
    }
    public function down(): void
    {
        // Не удаляем таблицы и данные: они могли существовать до запуска миграции.
    }
};
