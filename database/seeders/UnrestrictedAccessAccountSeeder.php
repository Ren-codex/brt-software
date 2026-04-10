<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UnrestrictedAccessAccountSeeder extends Seeder
{
    /**
     * Seed a dedicated account with unrestricted access across the application.
     */
    public function run(): void
    {
        $timestamp = now();

        DB::transaction(function () use ($timestamp) {
            $user = User::updateOrCreate(
                ['email' => 'all.access@example.com'],
                [
                    'username' => 'allaccess01',
                    'password' => Hash::make('password'),
                    'is_active' => 1,
                    'is_new' => 0,
                    'email_verified_at' => $timestamp,
                    'password_changed_at' => $timestamp,
                    'two_factor_confirmed_at' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                ]
            );

            $addedById = User::query()->whereKey(1)->exists() ? 1 : null;

            DB::table('user_roles')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'role_id' => 7,
                ],
                [
                    'is_active' => 1,
                    'added_by_id' => $addedById,
                    'removed_by_id' => null,
                    'removed_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );

            DB::table('employees')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'lastname' => 'Access',
                    'firstname' => 'All',
                    'middlename' => 'System',
                    'suffix' => null,
                    'mobile' => '09170000000',
                    'birthdate' => '',
                    'avatar' => 'noavatar.jpg',
                    'sex' => 'Male',
                    'religion' => 'N/A',
                    'address' => 'Seeded unrestricted account',
                    'position_id' => 1,
                    'email' => $user->email,
                    'is_regular' => 1,
                    'is_active' => 1,
                    'is_blacklisted' => 0,
                    'added_by_id' => $addedById,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );
        });
    }
}
