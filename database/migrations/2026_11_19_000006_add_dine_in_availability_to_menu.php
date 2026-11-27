<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['menu_items', 'menu_sections'] as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'dine_in_enabled')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('dine_in_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (['menu_items', 'menu_sections'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'dine_in_enabled')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('dine_in_enabled');
                });
            }
        }
    }
};
