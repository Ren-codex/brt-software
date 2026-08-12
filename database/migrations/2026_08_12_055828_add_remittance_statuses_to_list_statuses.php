<?php

use App\Models\ListStatus;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The remittance workflow (RemittanceClass) requires these two statuses,
     * but they were never present in list_statuses on existing databases:
     *  - for-verification: assigned to a newly prepared remittance and the
     *    default "For Verification" tab filters on it.
     *  - remitted: set when a remittance is marked as remitted.
     * Idempotent (firstOrCreate by slug) so it is safe on any environment.
     */
    public function up(): void
    {
        ListStatus::firstOrCreate(
            ['slug' => 'for-verification'],
            ['name' => 'For Verification', 'description' => 'For Verification status', 'text_color' => '#ffffff', 'bg_color' => '#0ea5e9']
        );

        ListStatus::firstOrCreate(
            ['slug' => 'remitted'],
            ['name' => 'Remitted', 'description' => 'Remitted status', 'text_color' => '#ffffff', 'bg_color' => '#3d8d7a']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ListStatus::whereIn('slug', ['for-verification', 'remitted'])->delete();
    }
};
