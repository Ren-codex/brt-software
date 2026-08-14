<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 'open' slug (used only by remittances) keeps its slug for the
     * queries and frontend filters keyed on it — only the display name
     * changes, since staff read "Open" as "not yet remitted" when it
     * actually means the cash is in and waiting on the approver.
     */
    public function up(): void
    {
        // Superseded by 2026_08_12_055828_add_remittance_statuses_to_list_statuses,
        // which creates a dedicated 'for-verification' status via firstOrCreate.
        // Renaming the 'open' row's name here would now collide with that row's
        // unique name constraint. Skip if that already happened.
        $alreadyHandled = DB::table('list_statuses')
            ->where('name', 'For Verification')
            ->where('slug', '!=', 'open')
            ->exists();

        if ($alreadyHandled) {
            return;
        }

        DB::table('list_statuses')
            ->where('slug', 'open')
            ->update([
                'name' => 'For Verification',
                'description' => 'For Verification status',
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
