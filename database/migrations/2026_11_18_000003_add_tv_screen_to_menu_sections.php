<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_sections') && !Schema::hasColumn('menu_sections', 'tv_screen')) {
            Schema::table('menu_sections', function (Blueprint $table) {
                $table->unsignedTinyInteger('tv_screen')
                    ->default(1)
                    ->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_sections') && Schema::hasColumn('menu_sections', 'tv_screen')) {
            Schema::table('menu_sections', function (Blueprint $table) {
                $table->dropColumn('tv_screen');
            });
        }
    }
};
