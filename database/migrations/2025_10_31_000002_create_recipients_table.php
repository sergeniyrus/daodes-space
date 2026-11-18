<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('recipients')) {
            Schema::create('recipients', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('imported_from')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Не удаляем таблицу: она могла существовать до запуска миграции.
    }
};
