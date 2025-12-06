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
                'name' => 'Active',
                'description' => 'Active status',
                'text_color' => '#ffffff',
                'bg_color' => '#28a745',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Inactive',
                'description' => 'Inactive status',
                'text_color' => '#ffffff',
                'bg_color' => '#6c757d',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Pending',
                'description' => 'Pending status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));

        
    }
}
