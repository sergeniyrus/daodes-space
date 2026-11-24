<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_stories')) {
            Schema::create('chat_stories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('ipfs_cid', 255);
                $table->string('media_type', 20);
                $table->string('mime_type', 150);
                $table->string('file_nonce', 255);
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('chat_story_keys')) {
            Schema::create('chat_story_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('chat_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('encrypted_key');
                $table->string('nonce', 255);
                $table->string('sender_public_key', 255);
                $table->timestamps();
                $table->unique(['story_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('chat_story_views')) {
            Schema::create('chat_story_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('chat_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('viewed_at');
                $table->timestamps();
                $table->unique(['story_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_story_views');
        Schema::dropIfExists('chat_story_keys');
        Schema::dropIfExists('chat_stories');
    }
};
