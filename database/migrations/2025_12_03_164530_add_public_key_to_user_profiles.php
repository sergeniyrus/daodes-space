<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    if (Schema::hasTable('user_profiles') && !Schema::hasColumn('user_profiles', 'public_key')) {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('public_key')->nullable();
        });
    }
}

public function down()
{
    // Не удаляем public_key: столбец мог существовать до этой миграции.
}
};
