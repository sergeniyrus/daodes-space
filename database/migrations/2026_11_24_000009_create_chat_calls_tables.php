<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_calls')) {
            Schema::create('chat_calls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
                $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
                $table->string('call_type', 12);
                $table->string('status', 20)->default('ringing');
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
                $table->index(['chat_id', 'status', 'created_at']);
                $table->index(['callee_id', 'status']);
            });
        }

        if (!Schema::hasTable('chat_call_signals')) {
            Schema::create('chat_call_signals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')->constrained('chat_calls')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->string('signal_type', 16);
                $table->longText('encrypted_payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['call_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_call_signals');
        Schema::dropIfExists('chat_calls');
    }
};
