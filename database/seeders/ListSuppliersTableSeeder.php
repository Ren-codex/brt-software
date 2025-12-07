<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListSuppliersTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('list_suppliers')->delete();

        \DB::table('list_suppliers')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Supplier A',
                'address' => '123 Main St',
                'contact_person' => 'John Doe',
                'contact_number' => '123-456-7890',
                'email' => 'john@supplier.com',
                'tin' => '123456789',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
