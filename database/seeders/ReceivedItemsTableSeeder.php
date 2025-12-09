<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReceivedItemsTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('received_items')->delete();

        \DB::table('received_items')->insert(array (
            0 =>
            array (
                'id' => 1,
                'received_id' => 1,
                'product_id' => 1,
                'quantity' => 100.0000,
                'unit_cost' => 10.0000,
                'total_cost' => 1000.0000,
                'po_item_id' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
