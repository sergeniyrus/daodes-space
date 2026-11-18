<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Поля могли быть добавлены вручную ранее.
         * Добавляем только отсутствующие колонки.
         */
        if (Schema::hasTable('customers')) {
            if (!Schema::hasColumn('customers', 'password')) {
                Schema::table('customers', function (Blueprint $table) {
                    $table->string('password')->nullable()->after('phone');
                });
            }

            if (!Schema::hasColumn('customers', 'remember_token')) {
                Schema::table('customers', function (Blueprint $table) {
                    $table->rememberToken();
                });
            }
        }

        /*
         * Таблица восстановления также могла быть создана вручную.
         */
        if (!Schema::hasTable('account_recovery_challenges')) {
            Schema::create('account_recovery_challenges', function (Blueprint $table) {
                $table->id();
                $table->string('account_type', 24);
                $table->unsignedBigInteger('account_id');
                $table->string('phone', 20);
                $table->char('code_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['account_type', 'account_id', 'created_at'], 'account_recovery_account_rate_index');
                $table->index(['account_type', 'account_id', 'id'], 'account_recovery_account_latest_index');
                $table->index(['phone', 'created_at'], 'account_recovery_phone_rate_index');
            });
        }
    }

    public function down(): void
    {
        /*
         * Не удаляем поля и таблицу автоматически:
         * они могли быть созданы вручную и содержать рабочие данные.
         */
    }
};
