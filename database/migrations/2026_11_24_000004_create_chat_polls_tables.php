<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_polls')) {
            Schema::create('chat_polls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
                $table->boolean('is_anonymous')->default(true);
                $table->boolean('allows_multiple')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('chat_poll_votes')) {
            Schema::create('chat_poll_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->char('voter_hash', 64)->nullable();
                $table->unsignedTinyInteger('option_id');
                $table->timestamps();

                $table->unique(['poll_id', 'user_id', 'option_id'], 'chat_poll_votes_user_option_unique');
                $table->unique(['poll_id', 'voter_hash', 'option_id'], 'chat_poll_votes_hash_option_unique');
                $table->index(['poll_id', 'option_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_poll_votes');
        Schema::dropIfExists('chat_polls');
    }
};
