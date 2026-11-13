<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // В БД хранится только SHA-256-хеш bearer-токена.
            $table->char('receipt_token_hash', 64)->nullable()->unique();
            $table->timestamp('receipt_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['receipt_token_hash']);
            $table->dropColumn([
                'receipt_token_hash',
                'receipt_token_expires_at',
            ]);
        });
    }
};
