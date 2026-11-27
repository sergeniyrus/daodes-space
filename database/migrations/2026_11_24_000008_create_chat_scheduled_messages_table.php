<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_scheduled_messages')) {
            return;
        }

        Schema::create('chat_scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('chat_topics')->nullOnDelete();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->longText('encrypted_message');
            $table->string('nonce', 255);
            $table->timestamp('scheduled_for');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('sent_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['chat_id', 'sender_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_scheduled_messages');
    }
};
