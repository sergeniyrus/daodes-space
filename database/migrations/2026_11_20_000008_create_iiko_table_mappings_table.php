<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iiko_table_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('iiko_organization_id', 36)->index();
            $table->string('daodes_table_number', 64);
            $table->string('iiko_table_id', 36);
            $table->string('iiko_table_name', 120);
            $table->string('restaurant_section_id', 36)->nullable();
            $table->string('restaurant_section_name', 120)->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'daodes_table_number'],
                'iiko_table_mappings_org_daodes_table_unique'
            );

            $table->unique(
                ['organization_id', 'iiko_table_id'],
                'iiko_table_mappings_org_iiko_table_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iiko_table_mappings');
    }
};
