<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRolesTableSeeder extends Seeder
{
    /**
     * Assign one primary role to each seeded user.
     */
    public function run()
    {
        $timestamp = now();

        DB::table('user_roles')->truncate();

        $userRoles = [
            [
                'id' => 1,
                'user_id' => 1,
                'role_id' => 1,
            ],
            [
                'id' => 2,
                'user_id' => 2,
                'role_id' => 2,
            ],
            [
                'id' => 3,
                'user_id' => 3,
                'role_id' => 3,
            ],
            [
                'id' => 4,
                'user_id' => 4,
                'role_id' => 4,
            ],
            [
                'id' => 5,
                'user_id' => 5,
                'role_id' => 5,
            ],
            [
                'id' => 6,
                'user_id' => 6,
                'role_id' => 6,
            ],
            [
                'id' => 7,
                'user_id' => 7,
                'role_id' => 7,
            ],
            [
                'id' => 8,
                'user_id' => 8,
                'role_id' => 8,
            ],
            [
                'id' => 9,
                'user_id' => 9,
                'role_id' => 9,
            ],
        ];

        $rows = array_map(function ($userRole) use ($timestamp) {
            return array_merge($userRole, [
                'is_active' => 1,
                'added_by_id' => 1,
                'removed_by_id' => null,
                'removed_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }, $userRoles);

        DB::table('user_roles')->insert($rows);
    }
}
