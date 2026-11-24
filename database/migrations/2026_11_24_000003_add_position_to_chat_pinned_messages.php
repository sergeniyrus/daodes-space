<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_pinned_messages') && !Schema::hasColumn('chat_pinned_messages', 'position')) {
            Schema::table('chat_pinned_messages', function (Blueprint $table) {
                $table->unsignedInteger('position')->default(0)->after('pinned_by');
                $table->index(['chat_id', 'position']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_pinned_messages') && Schema::hasColumn('chat_pinned_messages', 'position')) {
            Schema::table('chat_pinned_messages', function (Blueprint $table) {
                $table->dropIndex(['chat_id', 'position']);
                $table->dropColumn('position');
            });
        }
    }
};
