<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('failed_at');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('event_id', 191);
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();
            $table->string('event_type', 24);
            $table->char('payload_hash', 64);
            $table->string('processing_status', 24)->default('received');
            $table->string('processing_message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'event_id'],
                'payment_webhook_provider_event_unique'
            );
            $table->index(
                ['payment_id', 'processing_status'],
                'payment_webhook_payment_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
