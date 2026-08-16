<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class InventoryDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10): grants that preserve exactly
     * the access these roles already have today via the existing
     * role:Administrator,Warehouse Manager route-group gate, before this
     * plan adds a second, finer-grained permission layer inside it. Super
     * Admin needs no row — PermissionService bypasses it unconditionally.
     */
    public function run(): void
    {
        $inventory = Module::where('key', 'inventory')->first();
        if (!$inventory) {
            return; // modules/submodules catalog not seeded yet — nothing to grant against.
        }

        $grants = [
            ['role' => 'Administrator', 'level' => 'admin'],
            ['role' => 'Warehouse Manager', 'level' => 'encoder'],
            ['role' => 'Warehouse Manager', 'level' => 'approver'],
            ['role' => 'Warehouse Manager', 'level' => 'view'],
            // Warehouse Manager could void purchase orders/stock returns/received
            // stock via the 'approver'/'admin' gate before 'void' became its own
            // level -- grant it here so this rewiring doesn't silently revoke that.
            ['role' => 'Warehouse Manager', 'level' => 'void'],
        ];

        foreach ($grants as $grant) {
            $role = ListRole::where('name', $grant['role'])->first();
            if (!$role) {
                continue; // role doesn't exist in this environment — skip rather than fail the seeder.
            }

            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'module_id' => $inventory->id,
                'submodule_id' => null,
                'access_level' => $grant['level'],
            ]);
        }
    }
}
