<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menu_sections')) {
            return;
        }

        if (!Schema::hasColumn('menu_sections', 'delivery_enabled')) {
            Schema::table('menu_sections', function (Blueprint $table) {
                $table->boolean('delivery_enabled')->default(true)->after('is_active');
            });
        }

        if (!Schema::hasColumn('menu_sections', 'pickup_enabled')) {
            Schema::table('menu_sections', function (Blueprint $table) {
                $table->boolean('pickup_enabled')->default(true)->after('delivery_enabled');
            });
        }
    }

    public function down(): void
    {
        // Не удаляем существующие таблицы, столбцы и данные при откате.
    }
};
