<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_story_reactions')) {
            Schema::create('chat_story_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('chat_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reaction', 16);
                $table->timestamps();
                $table->unique(['story_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('chat_channel_story_reactions')) {
            Schema::create('chat_channel_story_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('chat_channel_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reaction', 16);
                $table->timestamps();
                $table->unique(['story_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_channel_story_reactions');
        Schema::dropIfExists('chat_story_reactions');
    }
};
