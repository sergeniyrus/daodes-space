<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iiko_connections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->unique()
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Encrypted by the IikoConnection model cast.
            $table->text('api_key');

            $table->string('iiko_organization_id', 36)->nullable()->index();
            $table->string('iiko_organization_name')->nullable();
            $table->string('terminal_group_id', 36)->nullable();
            $table->string('terminal_group_name')->nullable();
            $table->string('address_format_type', 32)->nullable();
            $table->string('external_menu_id', 36)->nullable();
            $table->string('price_category_id', 36)->nullable();

            $table->string('connection_status', 32)->default('pending')->index();

            $table->boolean('is_enabled')->default(false);
            $table->boolean('menu_sync_enabled')->default(false);
            $table->boolean('stop_list_sync_enabled')->default(false);
            $table->boolean('order_sync_enabled')->default(false);
            $table->boolean('status_sync_enabled')->default(false);
            $table->boolean('webhook_enabled')->default(false);

            $table->text('webhook_secret')->nullable();

            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_menu_sync_at')->nullable();
            $table->timestamp('last_stop_list_sync_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iiko_connections');
    }
};
