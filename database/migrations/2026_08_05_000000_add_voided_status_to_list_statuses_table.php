<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('list_statuses')->where('slug', 'voided')->exists()) {
            DB::table('list_statuses')->insert([
                'name' => 'Voided',
                'slug' => 'voided',
                'description' => 'Voided status',
                'text_color' => '#ffffff',
                'bg_color' => '#dc3545',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('list_statuses')->where('slug', 'voided')->delete();
    }
};
