<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menu_items')) return;
        $addAvailability = !Schema::hasColumn('menu_items', 'is_available');
        $addReason = !Schema::hasColumn('menu_items', 'unavailable_reason');
        if (!$addAvailability && !$addReason) return;
        Schema::table('menu_items', function (Blueprint $table) use ($addAvailability, $addReason) {
            if ($addAvailability) $table->boolean('is_available')->default(true);
            if ($addReason) $table->string('unavailable_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu_items')) return;
        $dropAvailability = Schema::hasColumn('menu_items', 'is_available');
        $dropReason = Schema::hasColumn('menu_items', 'unavailable_reason');
        if (!$dropAvailability && !$dropReason) return;
        Schema::table('menu_items', function (Blueprint $table) use ($dropAvailability, $dropReason) {
            if ($dropAvailability) $table->dropColumn('is_available');
            if ($dropReason) $table->dropColumn('unavailable_reason');
        });
    }
};
