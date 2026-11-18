<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organization_order_settings')) {
        if (!Schema::hasTable('organization_order_settings')) {
        Schema::create('organization_order_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->unique()
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Валюта не задаётся автоматически: её должен выбрать менеджер.
            $table->char('currency', 3)->nullable();

            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('minimum_order', 12, 2)->nullable();

            $table->boolean('orders_enabled')->default(false);
            $table->boolean('delivery_enabled')->default(false);
            $table->boolean('pickup_enabled')->default(false);
            $table->boolean('dine_in_enabled')->default(false);

            $table->boolean('online_payment_enabled')->default(false);
            $table->boolean('cash_payment_enabled')->default(false);
            $table->boolean('card_payment_enabled')->default(false);

            // Время подготовки/самовывоза в минутах. NULL означает,
            // что организация не обещает фиксированный срок.
            $table->unsignedSmallInteger('pickup_min_minutes')->nullable();

            $table->timestamps();
        });
        }
        }
    }

    public function down(): void
    {
        // Не удаляем таблицы и данные: они могли существовать до запуска миграции.
    }
};
