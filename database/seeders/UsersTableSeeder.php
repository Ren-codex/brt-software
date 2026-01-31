<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            0 =>
            array (
                'id' => 1,
                'username' => 'rbt0213',
                'email' => 'ren.zeon0213@gmail.com',
                'password' => Hash::make('b0uy4nt-4dm1n'),
                'is_active' => 1,
                'is_new' => 0,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'remember_token' => NULL,
                'email_verified_at' => '2024-05-15 08:46:33',
                'password_changed_at' => '2025-10-13 13:19:09',
                'two_factor_confirmed_at' => NULL,
                'created_at' => '2025-10-13 10:11:59',
                'updated_at' => '2025-10-14 16:36:35',
            ),
            1 =>
            array (
                'id' => 2,
                'username' => 'driver01',
                'email' => 'driver@example.com',
                'password' => Hash::make('password'),
                'is_active' => 1,
                'is_new' => 0,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'remember_token' => NULL,
                'email_verified_at' => '2024-05-15 08:46:33',
                'password_changed_at' => '2025-10-13 13:19:09',
                'two_factor_confirmed_at' => NULL,
                'created_at' => '2025-10-13 10:11:59',
                'updated_at' => '2025-10-14 16:36:35',
            ),
            2 =>
            array (
                'id' => 3,
                'username' => 'salesrep01',
                'email' => 'salesrep@example.com',
                'password' => Hash::make('password'),
                'is_active' => 1,
                'is_new' => 0,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'remember_token' => NULL,
                'email_verified_at' => '2024-05-15 08:46:33',
                'password_changed_at' => '2025-10-13 13:19:09',
                'two_factor_confirmed_at' => NULL,
                'created_at' => '2025-10-13 10:11:59',
                'updated_at' => '2025-10-14 16:36:35',
            ),
        ));

        
    }
}