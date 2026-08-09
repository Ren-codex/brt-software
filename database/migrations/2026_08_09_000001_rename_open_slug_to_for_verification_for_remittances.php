<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 'open' slug was kept when the display name changed to "For
     * Verification" (see 2026_08_05_000002) to avoid touching every query
     * keyed on it. This migration finishes that rename now that the slug
     * itself is being aligned with the label everywhere it's referenced.
     */
    public function up(): void
    {
        DB::table('list_statuses')
            ->where('slug', 'open')
            ->update([
                'slug' => 'for-verification',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('list_statuses')
            ->where('slug', 'for-verification')
            ->update([
                'slug' => 'open',
                'updated_at' => now(),
            ]);
    }
};
