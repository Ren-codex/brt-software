<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListLocationsTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {


        \DB::table('list_locations')->delete();

        \DB::table('list_locations')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Main Warehouse',
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'Branch Office',
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'Retail Store',
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));


    }
}
