<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_checkout_snapshots')) {
            return;
        }

        Schema::create('customer_checkout_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->json('payload');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['customer_id', 'idempotency_key'], 'customer_checkout_snapshot_key_unique');
            $table->index(['customer_id', 'organization_id'], 'customer_checkout_snapshot_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_checkout_snapshots');
    }
};
