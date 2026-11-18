<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('description');
            $table->boolean('delivery_enabled')->default(true)->after('is_active');
            $table->boolean('pickup_enabled')->default(true)->after('delivery_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn([
                'image_path',
                'delivery_enabled',
                'pickup_enabled',
            ]);
        });
    }
};
