<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_order_settings')) {
            Schema::table('organization_order_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('organization_order_settings', 'delivery_origin_latitude')) {
                    $table->decimal('delivery_origin_latitude', 10, 7)->nullable();
                }

                if (!Schema::hasColumn('organization_order_settings', 'delivery_origin_longitude')) {
                    $table->decimal('delivery_origin_longitude', 10, 7)->nullable();
                }
            });
        }

        if (!Schema::hasTable('organization_delivery_zones')) {
            Schema::create('organization_delivery_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->decimal('max_distance_km', 5, 2);
                $table->decimal('minimum_order_amount', 12, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['organization_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_delivery_zones');

        if (Schema::hasTable('organization_order_settings')) {
            $columns = [];
            if (Schema::hasColumn('organization_order_settings', 'delivery_origin_latitude')) {
                $columns[] = 'delivery_origin_latitude';
            }
            if (Schema::hasColumn('organization_order_settings', 'delivery_origin_longitude')) {
                $columns[] = 'delivery_origin_longitude';
            }
            if ($columns !== []) {
                Schema::table('organization_order_settings', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
