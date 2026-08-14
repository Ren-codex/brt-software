<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class SalesDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10): grants that preserve exactly
     * the access these roles already have today via hardcoded role-name
     * checks (Menu.vue's Sales Management link, and the canApprove/isAdmin
     * computed props in the Sales Vue components) before those checks are
     * replaced with can()/canAny() in a later task. Super Admin needs no
     * row — PermissionService bypasses it unconditionally.
     */
    public function run(): void
    {
        $sales = Module::where('key', 'sales')->first();
        if (!$sales) {
            return; // modules/submodules catalog not seeded yet — nothing to grant against.
        }

        $grants = [
            ['role' => 'Administrator', 'level' => 'admin'],
            ['role' => 'Sales Rep', 'level' => 'encoder'],
            ['role' => 'Sales Rep', 'level' => 'view'],
            ['role' => 'Area Business Manager', 'level' => 'approver'],
            ['role' => 'Area Business Manager', 'level' => 'view'],
        ];

        foreach ($grants as $grant) {
            $role = ListRole::where('name', $grant['role'])->first();
            if (!$role) {
                continue; // role doesn't exist in this environment — skip rather than fail the seeder.
            }

            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'module_id' => $sales->id,
                'submodule_id' => null,
                'access_level' => $grant['level'],
            ]);
        }
    }
}
