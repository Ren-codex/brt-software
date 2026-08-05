<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ListRolesTableSeeder extends Seeder
{
    /**
     * Role names must match exactly what the application checks for: the
     * `roles.includes(...)` guards in Shared/Layouts/Components/Menu.vue and the
     * `role:` route middleware in routes/web.php. A role that is not checked
     * anywhere grants nothing beyond the dashboard.
     *
     * Uses updateOrInsert rather than delete+insert because user_roles.role_id
     * is a restrictOnDelete foreign key, so deleting seeded roles fails as soon
     * as a single user has been assigned one.
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
                'name' => 'Human Resource Officer',
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
