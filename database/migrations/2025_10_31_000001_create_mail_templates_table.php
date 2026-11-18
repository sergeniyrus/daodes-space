<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('mail_templates')) {
            Schema::create('mail_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('subject');
                $table->text('body');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Не удаляем таблицу: она могла существовать до запуска миграции.
    }
};
