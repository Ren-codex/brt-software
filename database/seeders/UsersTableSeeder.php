<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Seed one sample user for each seeded role.
     */
    public function run()
    {
        $timestamp = now();

        DB::table('users')->delete();

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

        $rows = array_map(function ($user) use ($timestamp) {
            return array_merge($user, [
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
            ]);
        }, $users);

        DB::table('users')->insert($rows);
    }
}
