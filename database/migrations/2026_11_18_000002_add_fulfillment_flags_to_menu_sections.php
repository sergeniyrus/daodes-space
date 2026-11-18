<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(true)->after('is_active');
            $table->boolean('pickup_enabled')->default(true)->after('delivery_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_enabled',
                'pickup_enabled',
            ]);
        });
    }
};
