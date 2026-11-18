<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_notifications')) {
        if (!Schema::hasTable('order_notifications')) {
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // user = manager DAODES, employee = отдельная учётная запись персонала.
            $table->string('recipient_type', 20);
            $table->unsignedBigInteger('recipient_id');

            $table->string('event_type', 50);
            $table->string('title', 180);
            $table->string('message', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(
                ['recipient_type', 'recipient_id', 'organization_id', 'read_at'],
                'order_notifications_recipient_unread_index'
            );

            $table->index(
                ['organization_id', 'order_id', 'event_type'],
                'order_notifications_order_event_index'
            );
        });
        }
        }
    }

    public function down(): void
    {
        // Не удаляем таблицы и данные: они могли существовать до запуска миграции.
    }
};
