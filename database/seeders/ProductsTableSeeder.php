<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('products')->delete();

        \DB::table('products')->insert(array (
            0 =>
            array (
                'id' => 1,
                'pack_size' => 5,
                'unit_id' => 1, // Kilogram
                'brand_id' => 1, // Jasmine Rice
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
                'price' => 250.00, // Price per pack
                
            ),
            1 =>
            array (
                'id' => 2,
                'pack_size' => 10,
                'price' => 480.00, // Price per pack
                'unit_id' => 1, // Kilogram
                'brand_id' => 1, // Jasmine Rice
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 =>
            array (
                'id' => 3,
                'pack_size' => 5,
                'price' => 270.00, // Price per pack
                'unit_id' => 1, // Kilogram
                'brand_id' => 2, // Basmati Rice
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            3 =>
            array (
                'id' => 4,
                'pack_size' => 10,
                'price' => 520.00, // Price per pack
                'unit_id' => 1, // Kilogram
                'brand_id' => 2, // Basmati Rice
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            4 =>
            array (
                'id' => 5,
                'pack_size' => 5,
                'price' => 220.00, // Price per pack
                'unit_id' => 1, // Kilogram
                'brand_id' => 3, // Brown Rice
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            5 =>
            array (
                'id' => 6,
                'pack_size' => 1,
                'price' => 1200.00, // Price per sack
                'unit_id' => 2, // Sack
                'brand_id' => 4, // White Rice
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
