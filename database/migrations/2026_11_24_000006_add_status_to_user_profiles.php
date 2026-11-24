<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_profiles') && !Schema::hasColumn('user_profiles', 'status_text')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->string('status_text', 140)->nullable();
                $table->string('status_emoji', 16)->nullable();
                $table->timestamp('status_expires_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_profiles') && Schema::hasColumn('user_profiles', 'status_text')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->dropIndex(['status_expires_at']);
                $table->dropColumn(['status_text', 'status_emoji', 'status_expires_at']);
            });
        }
    }
};
