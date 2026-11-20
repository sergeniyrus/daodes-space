<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iiko_menu_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('menu_item_id')
                ->constrained('menu_items')
                ->cascadeOnDelete();

            $table->string('iiko_product_id', 36)->index();
            $table->string('iiko_product_name')->nullable();
            $table->string('iiko_group_id', 36)->nullable();
            $table->string('external_menu_id', 36)->nullable();
            $table->string('price_category_id', 36)->nullable();

            $table->decimal('last_iiko_price', 12, 2)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'menu_item_id'],
                'iiko_menu_mappings_org_menu_item_unique'
            );

            $table->index(
                ['organization_id', 'iiko_product_id'],
                'iiko_menu_mappings_org_product_index'
            );
        });

        Schema::create('iiko_stop_list_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('iiko_product_id', 36);
            $table->decimal('balance', 12, 3)->default(0);
            $table->boolean('is_available')->default(false);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'iiko_product_id'],
                'iiko_stop_list_items_org_product_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iiko_stop_list_items');
        Schema::dropIfExists('iiko_menu_mappings');
    }
};
