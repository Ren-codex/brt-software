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
                'definition' => 'Manages day-to-day system administration, including users, role assignments, and operational setup.',
            ],
            [
                'id' => 2,
                'name' => 'Sales Rep',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Handles customer accounts, sales orders, field activity, and related sales transactions.',
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
                'name' => 'Warehouse Manager',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Supervises receiving, stock movement, inventory monitoring, and warehouse operations.',
            ],
            [
                'id' => 5,
                'name' => 'Area Business Manager',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Oversees regional sales performance, team execution, and business development activities.',
            ],
            [
                'id' => 6,
                'name' => 'Accountant',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Manages financial records, payables, receivables, disbursements, and accounting reports.',
            ],
            [
                'id' => 7,
                'name' => 'Super Admin',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Has unrestricted access to all modules, settings, approvals, and master data across the system.',
            ],

            [
                'id' => 8,
                'name' => 'Mini Admin',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Has unrestricted access to all modules, settings, approvals, and master data across the system.',
            ],
            [
                'id' => 9,
                'name' => 'Logistic Coordinator',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Coordinates deliveries, shipment schedules, transport activity, and logistics-related documentation.',
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
