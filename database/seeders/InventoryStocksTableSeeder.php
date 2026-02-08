<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InventoryStocksTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('inventory_stocks')->delete();

        \DB::table('inventory_stocks')->insert(array (
            0 =>
            array (
                'id' => 1,
                'received_item_id' => 1, // Rice 5kg Sack
                'quantity' => 100.0000,
                'batch_code' => 'BATCH-0001',
                'retail_price' => 150.00,
                'wholesale_price' => 130.00,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            1 =>
            array (
                'id' => 2,
                'received_item_id' => 2, // Rice 10kg Sack
                'quantity' => 50.0000,
                'batch_code' => 'BATCH-0002',
                'retail_price' => 280.00,
                'wholesale_price' => 250.00,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 =>
            array (
                'id' => 3,
                'received_item_id' => 3, // Rice 25kg Sack
                'quantity' => 20.0000,
                'batch_code' => 'BATCH-0003',
                'retail_price' => 650.00,
                'wholesale_price' => 600.00,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
