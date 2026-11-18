<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            if (!Schema::hasColumn('orders', 'receipt_token_hash')) {
                Schema::table('orders', function (Blueprint $table) {
                    // В БД хранится только SHA-256-хеш bearer-токена.
                    $table->char('receipt_token_hash', 64)->nullable()->unique();
                });
            }

            if (!Schema::hasColumn('orders', 'receipt_token_expires_at')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->timestamp('receipt_token_expires_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // Не удаляем таблицы и данные: они могут существовать до запуска этой миграции.
    }
};
