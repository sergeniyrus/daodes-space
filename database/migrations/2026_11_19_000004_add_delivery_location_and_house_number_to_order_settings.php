<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_order_settings')) {
            if (!Schema::hasColumn('organization_order_settings', 'delivery_city')) {
                Schema::table('organization_order_settings', function (Blueprint $table) {
                    $table->string('delivery_city', 255)->nullable();
                });
            }

            if (!Schema::hasColumn('organization_order_settings', 'pickup_address')) {
                Schema::table('organization_order_settings', function (Blueprint $table) {
                    $table->string('pickup_address', 500)->nullable();
                });
            }
        }

        if (Schema::hasTable('customer_addresses') && !Schema::hasColumn('customer_addresses', 'house_number')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->string('house_number', 30)->nullable()->after('address_line');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_addresses') && Schema::hasColumn('customer_addresses', 'house_number')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropColumn('house_number');
            });
        }

        if (Schema::hasTable('organization_order_settings')) {
            if (Schema::hasColumn('organization_order_settings', 'pickup_address')) {
                Schema::table('organization_order_settings', function (Blueprint $table) {
                    $table->dropColumn('pickup_address');
                });
            }

            if (Schema::hasColumn('organization_order_settings', 'delivery_city')) {
                Schema::table('organization_order_settings', function (Blueprint $table) {
                    $table->dropColumn('delivery_city');
                });
            }
        }
    }
};
