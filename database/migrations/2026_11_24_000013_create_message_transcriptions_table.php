<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('message_transcriptions')) {
            Schema::create('message_transcriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->longText('encrypted_text');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_transcriptions');
    }
};
