<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('organizations', 'tv_screens_count')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->unsignedTinyInteger('tv_screens_count')
                    ->default(3)
                    ->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'tv_screens_count')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('tv_screens_count');
            });
        }
    }
};
