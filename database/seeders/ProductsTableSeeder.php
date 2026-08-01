<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductsTableSeeder extends Seeder
{
    public function run()
    {
        \DB::table('products')->delete();

        // unit_id:      1=Kg, 2=g, 3=Ton, 4=Lb
        // packaging_id: 1=Sack, 2=Bag, 3=Box, 4=Pouch
        // brand_id:     1=Jasmine, 2=Basmati, 3=Brown, 4=White, 5=Arborio, 6=Black, 7=Wild, 8=Parboiled

        \DB::table('products')->insert([
            // Jasmine Rice
            ['id' =>  1, 'code' => 'JRS5001',  'brand_id' => 1, 'packaging_id' => 1, 'weight' =>  5, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' =>  2, 'code' => 'JRS10001', 'brand_id' => 1, 'packaging_id' => 1, 'weight' => 10, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' =>  3, 'code' => 'JRS25001', 'brand_id' => 1, 'packaging_id' => 1, 'weight' => 25, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' =>  5, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' =>  4, 'code' => 'JRS50001', 'brand_id' => 1, 'packaging_id' => 1, 'weight' => 50, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' =>  5, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],

            // Basmati Rice
            ['id' =>  5, 'code' => 'BRS5001',  'brand_id' => 2, 'packaging_id' => 1, 'weight' =>  5, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' =>  6, 'code' => 'BRS10001', 'brand_id' => 2, 'packaging_id' => 1, 'weight' => 10, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' =>  7, 'code' => 'BRS25001', 'brand_id' => 2, 'packaging_id' => 1, 'weight' => 25, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' =>  5, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],

            // Brown Rice
            ['id' =>  8, 'code' => 'BRB2001',  'brand_id' => 3, 'packaging_id' => 2, 'weight' =>  2, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 20, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' =>  9, 'code' => 'BRB5001',  'brand_id' => 3, 'packaging_id' => 2, 'weight' =>  5, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 10, 'code' => 'BRB10001', 'brand_id' => 3, 'packaging_id' => 2, 'weight' => 10, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],

            // White Rice
            ['id' => 11, 'code' => 'WRS5001',  'brand_id' => 4, 'packaging_id' => 1, 'weight' =>  5, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 12, 'code' => 'WRS25001', 'brand_id' => 4, 'packaging_id' => 1, 'weight' => 25, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' =>  5, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 13, 'code' => 'WRS50001', 'brand_id' => 4, 'packaging_id' => 1, 'weight' => 50, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' =>  5, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],

            // Arborio Rice
            ['id' => 14, 'code' => 'ARB1001',  'brand_id' => 5, 'packaging_id' => 3, 'weight' =>  1, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 20, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 15, 'code' => 'ARB5001',  'brand_id' => 5, 'packaging_id' => 3, 'weight' =>  5, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],

            // Black Rice
            ['id' => 16, 'code' => 'BLP500001', 'brand_id' => 6, 'packaging_id' => 4, 'weight' => 500, 'unit_id' => 2, 'is_active' => 1, 'minimum_stock' => 30, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 17, 'code' => 'BLB1001',   'brand_id' => 6, 'packaging_id' => 2, 'weight' =>   1, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 20, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],

            // Wild Rice
            ['id' => 18, 'code' => 'WLB1001',   'brand_id' => 7, 'packaging_id' => 2, 'weight' =>   1, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 20, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 19, 'code' => 'WLP500001',  'brand_id' => 7, 'packaging_id' => 4, 'weight' => 500, 'unit_id' => 2, 'is_active' => 1, 'minimum_stock' => 30, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],

            // Parboiled Rice
            ['id' => 20, 'code' => 'PBS5001',  'brand_id' => 8, 'packaging_id' => 1, 'weight' =>  5, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' => 10, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 21, 'code' => 'PBS25001', 'brand_id' => 8, 'packaging_id' => 1, 'weight' => 25, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' =>  5, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 22, 'code' => 'PBS50001', 'brand_id' => 8, 'packaging_id' => 1, 'weight' => 50, 'unit_id' => 1, 'is_active' => 1, 'minimum_stock' =>  5, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ]);
    }
}
