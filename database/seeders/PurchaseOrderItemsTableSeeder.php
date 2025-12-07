<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PurchaseOrderItemsTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('purchase_order_items')->delete();

        \DB::table('purchase_order_items')->insert(array (
            0 =>
            array (
                'id' => 1,
                'po_id' => 1,
                'product_id' => 1,
                'quantity' => 100.0000,
                'unit_cost' => 10.0000,
                'total_cost' => 1000.0000,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
