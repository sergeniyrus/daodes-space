<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_reviews')) {
            Schema::create('order_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->boolean('is_public')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'is_public', 'created_at'], 'order_reviews_org_public_created_idx');
            });
        }

        if (!Schema::hasTable('organization_feedback')) {
            Schema::create('organization_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 20);
                $table->string('subject', 180);
                $table->text('message');
                $table->string('status', 20)->default('new');
                $table->text('reply')->nullable();
                $table->foreignId('replied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('replied_by_employee_id')->nullable()->constrained('organization_employees')->nullOnDelete();
                $table->timestamp('replied_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status', 'created_at'], 'organization_feedback_org_status_created_idx');
                $table->index(['customer_id', 'created_at'], 'organization_feedback_customer_created_idx');
            });
        }

        if (Schema::hasTable('order_notifications') && Schema::hasColumn('order_notifications', 'order_id')) {
            Schema::table('order_notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->change();
                if (!Schema::hasColumn('order_notifications', 'feedback_id')) {
                    $table->unsignedBigInteger('feedback_id')->nullable()->after('order_id');
                    $table->index(['feedback_id', 'event_type'], 'order_notifications_feedback_event_idx');
                }
            });
        }
    }

    public function down(): void
    {
        // Не удаляем пользовательские отзывы, обращения и уведомления при откате.
    }
};
