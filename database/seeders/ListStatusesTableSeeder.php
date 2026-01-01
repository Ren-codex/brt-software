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
                'slug' => 'pending',
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
                'slug' => 'cancelled',
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
                'slug' => 'sales-returned',
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
                'slug' => 'allowance-applied',
                'description' => 'Pending status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            4 => 
            array (
                'id' => 5,
                'name' => 'Approved',
                'slug' => 'approved',
                'description' => 'Approved status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            5 => 
            array (
                'id' => 6,
                'name' => 'Disapproved',
                'slug' => 'disapproved',
                'description' => 'Disapproved status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            6 =>    
            array (
                'id' => 7,
                'name' => 'Adjusted',
                'slug' => 'adjusted',
                'description' => 'Adjusted status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            7 => 
            array (
                'id' => 8,
                'name' => 'Invoiced',
                'slug' => 'invoiced',
                'description' => 'Invoiced status',
                'text_color' => '#ffffff',
                'bg_color' => '#28a745',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            8 => 
            array (
                'id' => 9,
                'name' => 'Closed',
                'slug' => 'closed',
                'description' => 'Closed status',
                'text_color' => '#ffffff',
                'bg_color' => '#000000',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));

        
    }
}
