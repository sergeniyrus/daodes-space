<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Эти поля расширяют существующую legacy-таблицу меню.
        // В тестовом окружении базовая таблица может создаваться отдельным helper позже.
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        if (!Schema::hasColumn('menu_items', 'image_path')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->string('image_path', 500)->nullable()->after('description');
            });
        }

        if (!Schema::hasColumn('menu_items', 'delivery_enabled')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->boolean('delivery_enabled')->default(true)->after('is_active');
            });
        }

        if (!Schema::hasColumn('menu_items', 'pickup_enabled')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->boolean('pickup_enabled')->default(true)->after('delivery_enabled');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        $columns = array_values(array_filter(
            ['image_path', 'delivery_enabled', 'pickup_enabled'],
            fn (string $column) => Schema::hasColumn('menu_items', $column)
        ));

        if ($columns !== []) {
            Schema::table('menu_items', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
