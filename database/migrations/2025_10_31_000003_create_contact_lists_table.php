<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasTable('contact_lists')) {
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('contact_list_recipient')) {
        Schema::create('contact_list_recipient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        // Не удаляем таблицы и данные: они могли существовать до запуска миграции.
    }
};
