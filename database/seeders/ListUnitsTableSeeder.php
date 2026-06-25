<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListUnitsTableSeeder extends Seeder
{
    public function run()
    {
        \DB::table('list_units')->delete();

        \DB::table('list_units')->insert([
            [
                'id'          => 1,
                'name'        => 'Kg',
                'description' => 'Kilogram',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
            [
                'id'          => 2,
                'name'        => 'g',
                'description' => 'Gram',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
            [
                'id'          => 3,
                'name'        => 'Ton',
                'description' => 'Metric Ton (1,000 kg)',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
            [
                'id'          => 4,
                'name'        => 'Lb',
                'description' => 'Pound',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
        ]);
    }
}
