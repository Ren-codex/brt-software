<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PurchaseOrdersTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('purchase_orders')->delete();

        \DB::table('purchase_orders')->insert(array (
            0 =>
            array (
                'id' => 1,
                'po_number' => 'PO001',
                'po_date' => '2025-01-01',
                'total_amount' => 1000.00,
                'status_id' => 1,
                'supplier_id' => 1,
                'created_by_id' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
