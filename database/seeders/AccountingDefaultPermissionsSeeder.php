<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class AccountingDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10/§15): Administrator is the
     * only role that actually satisfies the existing role:Administrator
     * gate on every Accounting route today, so it's the only one that
     * needs a preserved-access grant here — no role gets new access it
     * doesn't already have. Super Admin needs no row — PermissionService
     * bypasses it unconditionally.
     */
    public function run(): void
    {
        $module = Module::where('key', 'accounting')->first();
        if (!$module) {
            return; // modules catalog not seeded yet — nothing to grant against.
        }

        $role = ListRole::where('name', 'Administrator')->first();
        if (!$role) {
            return; // role doesn't exist in this environment — skip rather than fail the seeder.
        }

        RolePermission::firstOrCreate([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);
    }
}
