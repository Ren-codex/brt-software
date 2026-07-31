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
        $roles = [
            [
                'id' => 1,
                'name' => 'Administrator',
                'definition' => 'Has full system access, including managing users, roles, and all system configurations',
            ],
            [
                'id' => 2,
                'name' => 'Sales Rep',
                'definition' => 'Records sales orders, issues receipts, and remits collections.',
            ],
            [
                'id' => 3,
                'name' => 'Human Resource Officer',
                'definition' => 'Oversees HR operations such as employee management, leave approvals, and record verification.',
            ],
            [
                'id' => 4,
                'name' => 'Inventory Manager',
                'definition' => 'Maintains purchase orders, received stock, stock levels, and inventory adjustments.',
            ],
            [
                'id' => 5,
                'name' => 'Sales Manager',
                'definition' => 'Owns the customer book and monitors outstanding receivables.',
            ],
            [
                'id' => 6,
                'name' => 'Top Management',
                'definition' => 'Executive oversight. Not referenced by any menu or route guard yet.',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('list_roles')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name' => $role['name'],
                    'type' => 'Staff',
                    'is_active' => 1,
                    'definition' => $role['definition'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
