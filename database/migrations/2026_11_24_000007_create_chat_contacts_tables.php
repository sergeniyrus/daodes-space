<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_contact_requests')) {
            Schema::create('chat_contact_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 20)->default('pending');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
                $table->unique(['sender_id', 'recipient_id']);
                $table->index(['recipient_id', 'status']);
                $table->index(['sender_id', 'status']);
            });
        }

        if (!Schema::hasTable('chat_contact_blocks')) {
            Schema::create('chat_contact_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'blocked_user_id']);
                $table->index(['blocked_user_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_contact_blocks');
        Schema::dropIfExists('chat_contact_requests');
    }
};
