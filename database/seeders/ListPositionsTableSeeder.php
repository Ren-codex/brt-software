<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListPositionsTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        \DB::table('list_positions')->truncate();

        \DB::table('list_positions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'is_active' => 1,
                'title' => 'Cashier',
                'slug' => 'cashier',
                'is_regular' => 1,
                'rate_per_day' => 500.00,
                'created_at' => '2024-08-25 14:41:28',
                'updated_at' => '2024-08-25 14:41:28',
            ),

            1 => 
            array (
                'id' => 2,
                'is_active' => 1,
                'title' => 'Sales Rep',
                'slug' => 'sales-rep',
                'short' => 'SR',
                'is_regular' => 1,
                'salary_id' => 1,
                'created_at' => '2024-08-25 14:41:28',
                'updated_at' => '2024-08-25 14:41:28',
            ),

                 2 => 
            array (
                'id' => 3,
                'is_active' => 1,
                'title' => 'Driver',
                'slug' => 'driver',
                'short' => 'driver',
                'is_regular' => 1,
                'salary_id' => 1,
                'created_at' => '2024-08-25 14:41:28',
                'updated_at' => '2024-08-25 14:41:28',
            ),
        ));

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}