<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserRolesTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('user_roles')->truncate();
        
        \DB::table('user_roles')->insert(array (
            0 => 
            array (
                'id' => 1,
                'is_active' => 1,
                'user_id' => 1,
                'role_id' => 1,
                'added_by' => 1,
                'removed_by' => NULL,
                'removed_at' => NULL,
                'created_at' => '2025-10-14 21:50:24',
                'updated_at' => '2025-10-14 21:50:24',
            ),
        ));

        
    }
}