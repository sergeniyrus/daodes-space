<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'allow_copy_messages')) {
                $table->boolean('allow_copy_messages')->default(true);
            }
            if (!Schema::hasColumn('chats', 'allow_forward_messages')) {
                $table->boolean('allow_forward_messages')->default(true);
            }
        });

        if (!Schema::hasTable('chat_message_reports')) {
            Schema::create('chat_message_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('message_author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('message_author_name', 255);
                $table->text('message_text');
                $table->string('reason', 40);
                $table->string('status', 20)->default('new');
                $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();

                $table->unique(['message_id', 'reporter_id']);
                $table->index(['chat_id', 'status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reports');

        Schema::table('chats', function (Blueprint $table) {
            foreach (['allow_copy_messages', 'allow_forward_messages'] as $column) {
                if (Schema::hasColumn('chats', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
