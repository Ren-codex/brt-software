<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $existing = DB::table('list_statuses')->pluck('slug')->toArray();

        if (!in_array('overdue', $existing)) {
            DB::table('list_statuses')->insert([
                'name'        => 'Overdue',
                'slug'        => 'overdue',
                'description' => 'Invoice is past its due date with an outstanding balance',
                'text_color'  => '#ffffff',
                'bg_color'    => '#dc3545',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        if (!in_array('partially-returned', $existing)) {
            DB::table('list_statuses')->insert([
                'name'        => 'Partially Returned',
                'slug'        => 'partially-returned',
                'description' => 'Sales order has had some items returned',
                'text_color'  => '#000000',
                'bg_color'    => '#fd7e14',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('list_statuses')->whereIn('slug', ['overdue', 'partially-returned'])->delete();
    }
};
