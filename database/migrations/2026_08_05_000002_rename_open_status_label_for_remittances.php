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
        DB::table('list_statuses')
            ->where('slug', 'open')
            ->update([
                'name' => 'Open',
                'description' => 'Open status',
                'updated_at' => now(),
            ]);
    }
};
