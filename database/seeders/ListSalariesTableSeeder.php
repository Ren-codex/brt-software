<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListSalariesTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        \DB::table('list_salaries')->truncate();

        \DB::table('list_salaries')->insert(array (
            0 => 
            array (
                'id' => 1,
                'amount' => 15000.00,
                'created_at' => '2024-08-25 14:40:57',
                'updated_at' => '2024-08-25 14:40:57',
            ),

            1 => 
            array (
                'id' => 2,
                'amount' => 20000.00,
                'created_at' => '2024-08-25 14:40:57',
                'updated_at' => '2024-08-25 14:40:57',
            ),
        ));

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}