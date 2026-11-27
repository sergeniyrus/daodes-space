<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_pinned_messages')) {
            Schema::create('chat_pinned_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('pinned_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['chat_id', 'message_id']);
                $table->index(['chat_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_pinned_messages');
    }
};
