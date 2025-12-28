<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListStatusesTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('list_statuses')->delete();
        
        \DB::table('list_statuses')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Pending',
                'description' => 'Active status',
                'text_color' => '#ffffff',
                'bg_color' => '#28a745',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Cancelled',
                'description' => 'Inactive status',
                'text_color' => '#ffffff',
                'bg_color' => '#6c757d',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Sales Returned',
                'description' => 'Pending status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            3 => 
            array (
                'id' => 4,
                'name' => 'Allowance Applied',
                'description' => 'Pending status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            4 => 
            array (
                'id' => 5,
                'name' => 'approved',
                'description' => 'Approved status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            5 => 
            array (
                'id' => 6,
                'name' => 'disapproved',
                'description' => 'Disapproved status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            6 =>
            array (
                'id' => 6,
                'name' => 'liquidated',
                'description' => 'Liquidated status',
                'text_color' => '#000000',
                'bg_color' => '#35f2d2',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            7 =>
            array (
                'id' => 7,
                'name' => 'posted',
                'description' => 'Posted status',
                'text_color' => '#000000',
                'bg_color' => '#35f2d2',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));

        
    }
}
