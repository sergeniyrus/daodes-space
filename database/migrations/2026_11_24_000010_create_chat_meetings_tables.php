<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_meetings')) {
            Schema::create('chat_meetings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
                $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
                $table->longText('encrypted_title');
                $table->string('title_nonce', 255);
                $table->longText('encrypted_description')->nullable();
                $table->string('description_nonce', 255)->nullable();
                $table->timestamp('starts_at');
                $table->unsignedSmallInteger('duration_minutes')->default(30);
                $table->string('status', 20)->default('scheduled');
                $table->timestamps();
                $table->index(['chat_id', 'starts_at', 'status']);
            });
        }

        if (!Schema::hasTable('chat_meeting_participants')) {
            Schema::create('chat_meeting_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('chat_meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('response', 16)->default('pending');
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->timestamps();
                $table->unique(['meeting_id', 'user_id']);
                $table->index(['user_id', 'response']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_meeting_participants');
        Schema::dropIfExists('chat_meetings');
    }
};
