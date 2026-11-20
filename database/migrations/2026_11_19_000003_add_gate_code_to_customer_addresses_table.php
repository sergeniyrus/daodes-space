<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_addresses') && !Schema::hasColumn('customer_addresses', 'gate_code')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->string('gate_code', 100)->nullable()->after('intercom');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_addresses') && Schema::hasColumn('customer_addresses', 'gate_code')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropColumn('gate_code');
            });
        }
    }
};
