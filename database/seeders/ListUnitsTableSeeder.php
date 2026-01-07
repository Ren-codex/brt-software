<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListUnitsTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {


        \DB::table('list_units')->delete();

        \DB::table('list_units')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Kg',
                'description' => 'Kilogram',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'Sack',
                'description' => '',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'Bag',
                'description' => '',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            3 =>
            array (
                'id' => 4,
                'name' => 'Ton',
                'description' => '',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            4 =>
            array (
                'id' => 5,
                'name' => 'Pound',
                'description' => '',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));


    }
}
