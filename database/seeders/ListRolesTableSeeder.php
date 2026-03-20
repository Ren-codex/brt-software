<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ListRolesTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        $timestamp = now();

        $roles = [
            [
                'id' => 1,
                'name' => 'Administrator',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Has full system access, including managing users, roles, and all system configurations',
            ],
            [
                'id' => 2,
                'name' => 'Sales Rep',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Standard user role with access to personal records, DTR, leave filing, and travel requests.',
            ],
            [
                'id' => 3,
                'name' => 'HR Manager',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Oversees HR operations such as employee management, leave approvals, and record verification.',
            ],
            [
                'id' => 4,
                'name' => 'Inventory Manager',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Manages stock movement, inventory monitoring, and warehouse-related operations.',
            ],
            [
                'id' => 5,
                'name' => 'Top Management',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Provides executive approval and oversight across operational modules.',
            ],
        ];

        $rows = array_map(function ($role) use ($timestamp) {
            return array_merge($role, [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }, $roles);

        DB::table('list_roles')->upsert(
            $rows,
            ['id'],
            ['name', 'type', 'is_active', 'definition', 'updated_at']
        );
    }
}
