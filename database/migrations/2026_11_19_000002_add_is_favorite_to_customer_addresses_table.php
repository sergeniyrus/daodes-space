<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_addresses') && !Schema::hasColumn('customer_addresses', 'is_favorite')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->boolean('is_favorite')->default(false)->after('is_default');
                $table->index(['customer_id', 'is_favorite'], 'customer_address_favorite_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_addresses') && Schema::hasColumn('customer_addresses', 'is_favorite')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropIndex('customer_address_favorite_index');
                $table->dropColumn('is_favorite');
            });
        }
    }
};
