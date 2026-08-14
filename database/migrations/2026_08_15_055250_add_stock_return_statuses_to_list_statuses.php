<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * StockReturnClass (receive/approve flow) requires these statuses, but
     * on databases that were only ever migrated (not `db:seed`'d from
     * ListStatusesTableSeeder) they don't exist, causing "X status is not
     * configured" validation errors. Idempotent (skip if slug exists), same
     * pattern as the voided/remittance status migrations.
     */
    public function up(): void
    {
        $statuses = [
            ['name' => 'Pending', 'slug' => 'pending', 'description' => 'Pending status', 'text_color' => '#ffffff', 'bg_color' => '#28a745'],
            ['name' => 'Approved', 'slug' => 'approved', 'description' => 'Approved status', 'text_color' => '#000000', 'bg_color' => '#ffc107'],
            ['name' => 'Disapproved', 'slug' => 'disapproved', 'description' => 'Disapproved status', 'text_color' => '#000000', 'bg_color' => '#ffc107'],
            ['name' => 'Replaced', 'slug' => 'replaced', 'description' => 'For Replaced status', 'text_color' => '#ffffff', 'bg_color' => '#9b2acf'],
            ['name' => 'Loss', 'slug' => 'loss', 'description' => 'For Loss status', 'text_color' => '#ffffff', 'bg_color' => '#9b2acf'],
            ['name' => 'Completed', 'slug' => 'completed', 'description' => 'Completed status', 'text_color' => '#ffffff', 'bg_color' => '#87efed'],
        ];

        foreach ($statuses as $status) {
            if (!DB::table('list_statuses')->where('slug', $status['slug'])->exists()) {
                DB::table('list_statuses')->insert($status + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('list_statuses')->whereIn('slug', [
            'pending', 'approved', 'disapproved', 'replaced', 'loss', 'completed',
        ])->delete();
    }
};
