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
            1 =>
            array (
                'id' => 2,
                'name' => 'Golden Rice Supplies',
                'address' => '456 Rice Mill Road',
                'contact_person' => 'Maria Santos',
                'contact_number' => '234-567-8901',
                'email' => 'maria@goldenrice.com',
                'tin' => '234567890',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'Premium Rice Traders',
                'address' => '789 Commerce Ave',
                'contact_person' => 'Roberto Cruz',
                'contact_number' => '345-678-9012',
                'email' => 'roberto@premiumrice.com',
                'tin' => '345678901',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            3 =>
            array (
                'id' => 4,
                'name' => 'Bountiful Grains Inc.',
                'address' => '321 Farm Lane',
                'contact_person' => 'Elena Reyes',
                'contact_number' => '456-789-0123',
                'email' => 'elena@bountifulgrains.com',
                'tin' => '456789012',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            4 =>
            array (
                'id' => 5,
                'name' => 'Sunrise Rice Wholesale',
                'address' => '654 Sunrise Blvd',
                'contact_person' => 'James Wilson',
                'contact_number' => '567-890-1234',
                'email' => 'james@sunriserice.com',
                'tin' => '567890123',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            5 =>
            array (
                'id' => 6,
                'name' => 'Quality Rice Distributors',
                'address' => '987 Distribution Center',
                'contact_person' => 'Sarah Johnson',
                'contact_number' => '678-901-2345',
                'email' => 'sarah@qualityrice.com',
                'tin' => '678901234',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            6 =>
            array (
                'id' => 7,
                'name' => 'Philippine Rice Co.',
                'address' => '147 Manila Street',
                'contact_person' => 'Miguel Torres',
                'contact_number' => '789-012-3456',
                'email' => 'miguel@philipperice.com',
                'tin' => '789012345',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            7 =>
            array (
                'id' => 8,
                'name' => 'Asian Grain Suppliers',
                'address' => '258 Chinatown Ave',
                'contact_person' => 'Lin Wei',
                'contact_number' => '890-123-4567',
                'email' => 'linwei@asiangrain.com',
                'tin' => '890123456',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            8 =>
            array (
                'id' => 9,
                'name' => 'Fresh Palay Direct',
                'address' => '369 Farmers Market',
                'contact_person' => 'Pedro Martinez',
                'contact_number' => '901-234-5678',
                'email' => 'pedro@freshpalay.com',
                'tin' => '901234567',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            9 =>
            array (
                'id' => 10,
                'name' => 'Mouthful Rice Enterprise',
                'address' => '741 Warehouse District',
                'contact_person' => 'Catherine Lee',
                'contact_number' => '012-345-6789',
                'email' => 'catherine@mouthfulrice.com',
                'tin' => '012345678',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            10 =>
            array (
                'id' => 11,
                'name' => 'Rural Harvest Rice',
                'address' => '852 Countryside Road',
                'contact_person' => 'Antonio Baustista',
                'contact_number' => '123-456-7891',
                'email' => 'antonio@ruralharvest.com',
                'tin' => '112345678',
                'is_active' => 1,
                'is_blacklisted' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
    }
}
