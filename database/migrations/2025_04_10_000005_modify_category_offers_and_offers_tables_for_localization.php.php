<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyCategoryOffersAndOffersTablesForLocalization extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_offers')) {
            if (Schema::hasColumn('category_offers', 'name') && !Schema::hasColumn('category_offers', 'name_ru')) {
                Schema::table('category_offers', function (Blueprint $table) {
                    $table->renameColumn('name', 'name_ru');
                });
            }

            if (!Schema::hasColumn('category_offers', 'name_en')) {
                Schema::table('category_offers', function (Blueprint $table) {
                    $after = Schema::hasColumn('category_offers', 'name_ru') ? 'name_ru' : 'name';
                    $table->string('name_en', 255)->nullable()->after($after);
                });
            }
        }

        if (Schema::hasTable('offers')) {
            if (Schema::hasColumn('offers', 'title') && !Schema::hasColumn('offers', 'title_ru')) {
                Schema::table('offers', function (Blueprint $table) {
                    $table->renameColumn('title', 'title_ru');
                });
            }

            if (!Schema::hasColumn('offers', 'title_en')) {
                Schema::table('offers', function (Blueprint $table) {
                    $after = Schema::hasColumn('offers', 'title_ru') ? 'title_ru' : 'title';
                    $table->string('title_en', 255)->nullable()->after($after);
                });
            }

            if (Schema::hasColumn('offers', 'content') && !Schema::hasColumn('offers', 'content_ru')) {
                Schema::table('offers', function (Blueprint $table) {
                    $table->renameColumn('content', 'content_ru');
                });
            }

            if (!Schema::hasColumn('offers', 'content_en')) {
                Schema::table('offers', function (Blueprint $table) {
                    $after = Schema::hasColumn('offers', 'content_ru') ? 'content_ru' : 'content';
                    $table->text('content_en')->nullable()->after($after);
                });
            }
        }
    }

    public function down(): void
    {
        // Не удаляем локализованные поля: они могли существовать до запуска миграции.
    }
}
