<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iiko_order_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('iiko_order_id', 36)->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->string('sync_status', 24)->default('pending')->index();
            $table->string('iiko_status', 80)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->char('request_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'sync_status']);
            $table->index(['organization_id', 'iiko_order_id']);
        });

        Schema::create('iiko_sync_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('iiko_connection_id')
                ->constrained('iiko_connections')
                ->cascadeOnDelete();

            $table->string('sync_type', 40)->index();
            $table->string('status', 24)->index();
            $table->string('message', 500)->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('iiko_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('iiko_connection_id')
                ->constrained('iiko_connections')
                ->cascadeOnDelete();

            // Stable deduplication key. Do not store the complete webhook body:
            // it may contain customer phone numbers and delivery addresses.
            $table->char('event_key', 64)->unique();
            $table->string('event_type', 80)->index();
            $table->string('iiko_order_id', 36)->nullable()->index();
            $table->string('event_status', 80)->nullable();
            $table->string('processing_status', 24)->default('pending')->index();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iiko_webhook_events');
        Schema::dropIfExists('iiko_sync_logs');
        Schema::dropIfExists('iiko_order_mappings');
    }
};
