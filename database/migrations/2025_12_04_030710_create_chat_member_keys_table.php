<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_member_keys')) {
            Schema::create('chat_member_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('encrypted_key'); // base64
                $table->string('nonce');         // base64
                $table->timestamps();

                $table->unique(['chat_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        // Не удаляем ключи участников чатов: они могли существовать до запуска миграции.
    }
};
