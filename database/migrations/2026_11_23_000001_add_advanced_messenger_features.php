<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_member_keys') && !Schema::hasColumn('chat_member_keys', 'sender_public_key')) {
            Schema::table('chat_member_keys', function (Blueprint $table) {
                $table->string('sender_public_key', 255)->nullable();
            });
        }

        if (Schema::hasTable('chat_user')) {
            Schema::table('chat_user', function (Blueprint $table) {
                if (!Schema::hasColumn('chat_user', 'role')) {
                    $table->string('role', 24)->default('member');
                }

                if (!Schema::hasColumn('chat_user', 'joined_at')) {
                    $table->timestamp('joined_at')->nullable();
                }

                if (!Schema::hasColumn('chat_user', 'muted_until')) {
                    $table->timestamp('muted_until')->nullable();
                }
            });
        }

        if (Schema::hasTable('chat_user') && Schema::hasTable('chats') && Schema::hasColumn('chat_user', 'role')) {
            DB::table('chats')
                ->select(['id', 'initiator_id'])
                ->whereNotNull('initiator_id')
                ->orderBy('id')
                ->chunkById(500, function ($chats) {
                    foreach ($chats as $chat) {
                        DB::table('chat_user')
                            ->where('chat_id', $chat->id)
                            ->where('user_id', $chat->initiator_id)
                            ->update(['role' => 'owner']);
                    }
                });
        }

        if (!Schema::hasTable('chat_topics')) {
            Schema::create('chat_topics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_closed')->default(false);
                $table->timestamps();

                $table->index(['chat_id', 'is_closed']);
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (!Schema::hasColumn('messages', 'topic_id')) {
                    $table->foreignId('topic_id')
                        ->nullable()
                        ->constrained('chat_topics')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('messages', 'message_type')) {
                    $table->string('message_type', 24)->default('text');
                }
            });
        }

        if (!Schema::hasTable('message_attachments')) {
            Schema::create('message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->string('ipfs_cid', 128);
                $table->string('media_type', 24)->default('file');
                $table->unsignedBigInteger('byte_size')->default(0);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();

                $table->index('ipfs_cid');
                $table->index(['message_id', 'position']);
            });
        }

        if (!Schema::hasTable('chat_uploads')) {
            Schema::create('chat_uploads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('ipfs_cid', 128);
                $table->string('media_type', 24)->default('file');
                $table->unsignedBigInteger('byte_size')->default(0);
                $table->timestamp('expires_at')->index();
                $table->timestamps();

                $table->index(['chat_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('message_mentions')) {
            Schema::create('message_mentions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['message_id', 'user_id']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('message_reactions')) {
            Schema::create('message_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reaction', 32);
                $table->timestamps();

                $table->unique(['message_id', 'user_id', 'reaction']);
                $table->index(['message_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('chat_uploads');
        Schema::dropIfExists('message_attachments');

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'topic_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropForeign(['topic_id']);
                $table->dropColumn('topic_id');
            });
        }

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'message_type')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('message_type');
            });
        }

        Schema::dropIfExists('chat_topics');

        if (Schema::hasTable('chat_member_keys') && Schema::hasColumn('chat_member_keys', 'sender_public_key')) {
            Schema::table('chat_member_keys', function (Blueprint $table) {
                $table->dropColumn('sender_public_key');
            });
        }

        if (Schema::hasTable('chat_user')) {
            Schema::table('chat_user', function (Blueprint $table) {
                foreach (['role', 'joined_at', 'muted_until'] as $column) {
                    if (Schema::hasColumn('chat_user', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
