<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListBrandsTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {


        \DB::table('list_brands')->delete();

        \DB::table('list_brands')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Jasmine Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'Basmati Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'Brown Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            3 =>
            array (
                'id' => 4,
                'name' => 'White Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            4 =>
            array (
                'id' => 5,
                'name' => 'Arborio Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            5 =>
            array (
                'id' => 6,
                'name' => 'Black Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            6 =>
            array (
                'id' => 7,
                'name' => 'Wild Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            7 =>
            array (
                'id' => 8,
                'name' => 'Parboiled Rice',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));


    }
}
