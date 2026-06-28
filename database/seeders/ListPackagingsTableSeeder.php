<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListPackagingsTableSeeder extends Seeder
{
    public function run()
    {
        \DB::table('list_packagings')->delete();

        \DB::table('list_packagings')->insert([
            [
                'id'          => 1,
                'name'        => 'Sack',
                'description' => 'Woven polypropylene sack',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
            [
                'id'          => 2,
                'name'        => 'Bag',
                'description' => 'Standard plastic or paper bag',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
            [
                'id'          => 3,
                'name'        => 'Box',
                'description' => 'Cardboard box',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
            [
                'id'          => 4,
                'name'        => 'Pouch',
                'description' => 'Small retail pouch',
                'created_at'  => '2025-01-01 00:00:00',
                'updated_at'  => '2025-01-01 00:00:00',
            ],
        ]);
    }
}
