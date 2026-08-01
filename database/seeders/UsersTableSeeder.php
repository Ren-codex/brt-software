<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Seed one sample user for each seeded role.
     *
     * Uses updateOrInsert so the seeder can be re-run without wiping users
     * that other tables already reference (customers, purchase orders, etc.
     * hardcode added_by_id/created_by_id against these seeded ids).
     */
    public function run()
    {
        $timestamp = now();

        $users = [
            [
                'id' => 1,
                'username' => 'administrator01',
                'email' => 'administrator@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 2,
                'username' => 'salesrep01',
                'email' => 'salesrep@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 3,
                'username' => 'hrmanager01',
                'email' => 'hrmanager@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 4,
                'username' => 'warehouse01',
                'email' => 'warehouse.manager@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 5,
                'username' => 'areabusiness01',
                'email' => 'area.business.manager@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 6,
                'username' => 'accountant01',
                'email' => 'accountant@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 7,
                'username' => 'superadmin01',
                'email' => 'super.admin@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 8,
                'username' => 'miniadmin01',
                'email' => 'mini.admin@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => 9,
                'username' => 'logistics01',
                'email' => 'logistic.coordinator@example.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['id' => $user['id']],
                [
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'password' => $user['password'],
                    'is_active' => 1,
                    'is_new' => 0,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'remember_token' => null,
                    'email_verified_at' => $timestamp,
                    'password_changed_at' => $timestamp,
                    'two_factor_confirmed_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );
        }
    }
}
