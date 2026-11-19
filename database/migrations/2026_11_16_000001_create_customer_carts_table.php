
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_carts')) {
            return;
        }

        Schema::create('customer_carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->json('items');
            $table->timestamps();

            $table->unique(
                ['customer_id', 'organization_id'],
                'customer_cart_customer_organization_unique'
            );

            $table->index(
                ['customer_id', 'updated_at'],
                'customer_cart_customer_updated_index'
            );
        });
    }

    public function down(): void
    {
        // Не удаляем существующую корзину с рабочими данными.
    }
};
