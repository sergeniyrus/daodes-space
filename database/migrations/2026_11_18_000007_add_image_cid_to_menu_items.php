<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items') && !Schema::hasColumn('menu_items', 'image_cid')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->string('image_cid', 255)->nullable()->after('image_path');
            });
        }
    }

    public function down(): void
    {
        // Не удаляем существующие таблицы, столбцы и данные при откате.
    }
};
