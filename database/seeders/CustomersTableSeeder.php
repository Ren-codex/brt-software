<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CustomersTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('customers')->delete();
        
        \DB::table('customers')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Juan Dela Cruz',
                'address' => '123 Rizal Street, Manila City',
                'contact_number' => '09171234567',
                'email' => 'juan.delacruz@email.com',
                'is_active' => 1,
                'is_regular' => 1,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-15 08:00:00',
                'updated_at' => '2025-01-15 08:00:00',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'ABC Trading Corporation',
                'address' => '456 Quezon Avenue, Quezon City',
                'contact_number' => '09181234567',
                'email' => 'info@abctrading.com',
                'is_active' => 1,
                'is_regular' => 1,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-16 09:30:00',
                'updated_at' => '2025-01-16 09:30:00',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Maria Santos',
                'address' => '789 Bonifacio Street, Makati City',
                'contact_number' => '09191234567',
                'email' => 'maria.santos@email.com',
                'is_active' => 1,
                'is_regular' => 0,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-17 10:15:00',
                'updated_at' => '2025-01-17 10:15:00',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'XYZ Enterprises',
                'address' => '321 Ayala Avenue, Makati City',
                'contact_number' => '09201234567',
                'email' => 'contact@xyzenterprises.com',
                'is_active' => 1,
                'is_regular' => 1,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-18 11:00:00',
                'updated_at' => '2025-01-18 11:00:00',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Pedro Reyes',
                'address' => '654 Taft Avenue, Pasay City',
                'contact_number' => '09211234567',
                'email' => NULL,
                'is_active' => 1,
                'is_regular' => 0,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-19 13:45:00',
                'updated_at' => '2025-01-19 13:45:00',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Global Solutions Inc.',
                'address' => '987 Ortigas Avenue, Pasig City',
                'contact_number' => '09221234567',
                'email' => 'sales@globalsolutions.com',
                'is_active' => 1,
                'is_regular' => 1,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-20 14:30:00',
                'updated_at' => '2025-01-20 14:30:00',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Ana Garcia',
                'address' => '147 España Boulevard, Manila City',
                'contact_number' => '09231234567',
                'email' => 'ana.garcia@email.com',
                'is_active' => 0,
                'is_regular' => 0,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-21 15:00:00',
                'updated_at' => '2025-01-21 15:00:00',
            ),
            7 =>
            array (
                'id' => 8,
                'name' => 'Prime Distributors',
                'address' => '258 Commonwealth Avenue, Quezon City',
                'contact_number' => '09241234567',
                'email' => 'orders@primedist.com',
                'is_active' => 1,
                'is_regular' => 1,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-22 16:20:00',
                'updated_at' => '2025-01-22 16:20:00',
            ),
            8 =>
            array (
                'id' => 9,
                'name' => 'Roberto Fernandez',
                'address' => '369 Roxas Boulevard, Pasay City',
                'contact_number' => '09251234567',
                'email' => NULL,
                'is_active' => 1,
                'is_regular' => 0,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-23 08:45:00',
                'updated_at' => '2025-01-23 08:45:00',
            ),
            9 =>
            array (
                'id' => 10,
                'name' => 'Mega Mart Retail',
                'address' => '741 Shaw Boulevard, Mandaluyong City',
                'contact_number' => '09261234567',
                'email' => 'procurement@megamart.com',
                'is_active' => 1,
                'is_regular' => 1,
                'is_blacklisted' => 0,
                'added_by_id' => 1,
                'created_at' => '2025-01-24 09:00:00',
                'updated_at' => '2025-01-24 09:00:00',
            ),
        ));

        
    }
}
