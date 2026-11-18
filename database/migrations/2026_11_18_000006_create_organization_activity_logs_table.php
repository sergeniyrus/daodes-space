<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organizations') || Schema::hasTable('organization_activity_logs')) {
            return;
        }

        Schema::create('organization_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 255);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at'], 'org_activity_org_created_idx');
            $table->index(['subject_type', 'subject_id'], 'org_activity_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_activity_logs');
    }
};
