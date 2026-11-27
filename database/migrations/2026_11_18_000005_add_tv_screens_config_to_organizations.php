<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations') && !Schema::hasColumn('organizations', 'tv_screens_config')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->json('tv_screens_config')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Не удаляем существующие таблицы, столбцы и данные при откате.
    }
};
