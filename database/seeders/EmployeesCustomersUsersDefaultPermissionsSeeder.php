<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class EmployeesCustomersUsersDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10/§13): grants that preserve
     * exactly the access these roles already have today — Employees and
     * Customers have no route-level gate at all right now, so without this
     * seeding, turning on enforcement would immediately lock out HR Manager
     * and Mini Admin/Administrator respectively. Super Admin needs no row —
     * PermissionService bypasses it unconditionally.
     */
    public function run(): void
    {
        $grants = [
            ['role' => 'Administrator', 'module' => 'employees', 'level' => 'admin'],
            ['role' => 'Administrator', 'module' => 'customers', 'level' => 'admin'],
            ['role' => 'Administrator', 'module' => 'user_management', 'level' => 'admin'],
            ['role' => 'HR Manager', 'module' => 'employees', 'level' => 'admin'],
            ['role' => 'Mini Admin', 'module' => 'customers', 'level' => 'admin'],
        ];

        foreach ($grants as $grant) {
            $module = Module::where('key', $grant['module'])->first();
            if (!$module) {
                continue; // modules catalog not seeded yet — nothing to grant against.
            }

            $role = ListRole::where('name', $grant['role'])->first();
            if (!$role) {
                continue; // role doesn't exist in this environment — skip rather than fail the seeder.
            }

            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'module_id' => $module->id,
                'submodule_id' => null,
                'access_level' => $grant['level'],
            ]);
        }
    }
}
