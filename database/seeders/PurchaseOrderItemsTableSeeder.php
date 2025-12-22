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
            1 =>
            array (
                'id' => 2,
                'po_id' => 1,
                'product_id' => 2,
                'quantity' => 50.0000,
                'unit_cost' => 15.0000,
                'total_cost' => 750.0000,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 =>
            array (
                'id' => 3,
                'po_id' => 1,
                'product_id' => 3,
                'quantity' => 200.0000,
                'unit_cost' => 8.0000,
                'total_cost' => 1600.0000,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            3 =>
            array (
                'id' => 4,
                'po_id' => 1,
                'product_id' => 4,
                'quantity' => 75.0000,
                'unit_cost' => 12.0000,
                'total_cost' => 900.0000,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            4 =>
            array (
                'id' => 5,
                'po_id' => 1,
                'product_id' => 5,
                'quantity' => 150.0000,
                'unit_cost' => 9.0000,
                'total_cost' => 1350.0000,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            5 =>
            array (
                'id' => 6,
                'po_id' => 1,
                'product_id' => 6,
                'quantity' => 25.0000,
                'unit_cost' => 20.0000,
                'total_cost' => 500.0000,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
