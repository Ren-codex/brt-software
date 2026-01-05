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
                'short' => 'CASHIER',
                'is_regular' => 1,
                'salary_id' => 1,
                'created_at' => '2024-08-25 14:41:28',
                'updated_at' => '2024-08-25 14:41:28',
            ),
        ));

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}