<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iiko_payment_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('iiko_organization_id', 36)->index();
            $table->string('daodes_payment_method', 40);
            $table->string('iiko_payment_type_id', 36);
            $table->string('iiko_payment_type_name');
            $table->string('payment_type_kind', 40)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'daodes_payment_method'],
                'iiko_payment_mappings_org_method_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iiko_payment_mappings');
    }
};
