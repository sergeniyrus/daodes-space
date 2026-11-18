<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_phone_challenges')) {
        Schema::create('employee_phone_challenges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('organization_employees')
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // HMAC-SHA256; исходный одноразовый код никогда не хранится.
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['employee_id', 'created_at'],
                'employee_phone_challenge_rate_index'
            );

            $table->index(
                ['employee_id', 'organization_id', 'id'],
                'employee_phone_challenge_scope_index'
            );
        });
        }
    }

    public function down(): void
    {
        // Не удаляем таблицы и данные: они могут существовать до запуска этой миграции.
    }
};
