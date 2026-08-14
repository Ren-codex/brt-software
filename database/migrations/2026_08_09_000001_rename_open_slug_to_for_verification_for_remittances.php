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
        // Superseded by 2026_08_12_055828_add_remittance_statuses_to_list_statuses,
        // which creates a dedicated 'for-verification' status via firstOrCreate.
        // Renaming the 'open' row's slug here would now collide with that row's
        // unique slug constraint. Skip if that already happened.
        $alreadyHandled = DB::table('list_statuses')->where('slug', 'for-verification')->exists();

        if ($alreadyHandled) {
            return;
        }

        DB::table('list_statuses')
            ->where('slug', 'open')
            ->update([
                'slug' => 'for-verification',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally a no-op: the 'for-verification' status is now owned by
        // 2026_08_12_055828_add_remittance_statuses_to_list_statuses. Reverting
        // here could corrupt that row. Roll back that migration instead if the
        // remittance status setup needs to be undone.
    }
};
