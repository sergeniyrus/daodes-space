<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Retired: voice transcripts are displayed on demand and are never persisted.
 *
 * This migration remains as a no-op so fresh installations do not create a
 * transcript table and existing installations keep their historical schema
 * untouched. Existing rows, if any, are deliberately not deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty.
    }

    public function down(): void
    {
        // Intentionally empty: do not delete historical data automatically.
    }
};
