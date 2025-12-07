<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReceivedStocksTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('received_stocks')->insert(array (
            0 =>
            array (
                'id' => 1,
                'po_id' => 1,
                'supplier_id' => 1,
                'received_date' => '2025-01-01',
                'batch_code' => 'CODE-1106-0001',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
