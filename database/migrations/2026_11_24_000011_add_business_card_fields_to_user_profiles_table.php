<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('card_position', 120)->nullable();
            $table->string('card_company', 120)->nullable();
            $table->string('card_location', 120)->nullable();
            $table->text('card_about')->nullable();
            $table->string('card_website', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'card_position',
                'card_company',
                'card_location',
                'card_about',
                'card_website',
            ]);
        });
    }
};
