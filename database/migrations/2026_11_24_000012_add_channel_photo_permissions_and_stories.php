<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'photo_cid')) {
                $table->string('photo_cid', 255)->nullable();
            }
            if (!Schema::hasColumn('chats', 'allow_admin_photo')) {
                $table->boolean('allow_admin_photo')->default(false);
            }
            if (!Schema::hasColumn('chats', 'allow_moderator_photo')) {
                $table->boolean('allow_moderator_photo')->default(false);
            }
            if (!Schema::hasColumn('chats', 'allow_admin_stories')) {
                $table->boolean('allow_admin_stories')->default(false);
            }
            if (!Schema::hasColumn('chats', 'allow_moderator_stories')) {
                $table->boolean('allow_moderator_stories')->default(false);
            }
        });

        if (!Schema::hasTable('chat_channel_stories')) {
            Schema::create('chat_channel_stories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
                $table->foreignId('published_by')->constrained('users')->cascadeOnDelete();
                $table->string('ipfs_cid', 255);
                $table->string('media_type', 20);
                $table->string('mime_type', 150);
                $table->string('file_nonce', 255);
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->index(['chat_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('chat_channel_story_keys')) {
            Schema::create('chat_channel_story_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('chat_channel_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('encrypted_key');
                $table->string('nonce', 255);
                $table->string('sender_public_key', 255);
                $table->timestamps();
                $table->unique(['story_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('chat_channel_story_views')) {
            Schema::create('chat_channel_story_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('chat_channel_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('viewed_at');
                $table->timestamps();
                $table->unique(['story_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_channel_story_views');
        Schema::dropIfExists('chat_channel_story_keys');
        Schema::dropIfExists('chat_channel_stories');

        Schema::table('chats', function (Blueprint $table) {
            foreach ([
                'photo_cid',
                'allow_admin_photo',
                'allow_moderator_photo',
                'allow_admin_stories',
                'allow_moderator_stories',
            ] as $column) {
                if (Schema::hasColumn('chats', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
