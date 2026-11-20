<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_order_settings', function (Blueprint $table) {
            $table->json('working_hours')->nullable();
            $table->string('timezone', 64)->default('Asia/Krasnoyarsk');
            $table->boolean('preorders_enabled')->default(true);
            $table->unsignedSmallInteger('order_lead_minutes')->default(30);
            $table->unsignedSmallInteger('time_slot_minutes')->default(15);
        });
    }

    public function down(): void
    {
        Schema::table('organization_order_settings', function (Blueprint $table) {
            $table->dropColumn([
                'working_hours',
                'timezone',
                'preorders_enabled',
                'order_lead_minutes',
                'time_slot_minutes',
            ]);
        });
    }
};
