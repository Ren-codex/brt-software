<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SalesDefaultPermissionsSeeder grants these same rows, but it's only wired
 * into DatabaseSeeder::run() (never invoked from a migration), so any
 * environment that was already migrated before the seeder was added never
 * received them. In practice this meant Sales Rep had no
 * 'sales'/encoder grant, so SalesOrderRequest::authorize() (which requires
 * 'sales.sales_orders.encoder' — module-wide grants satisfy any submodule)
 * rejected a Sales Rep trying to save a Sales Return. Insert the same grants
 * here, idempotently, so they land on every environment regardless of seeder
 * history.
 */
return new class extends Migration
{
    private array $grants = [
        ['role' => 'Administrator', 'level' => 'admin'],
        ['role' => 'Sales Rep', 'level' => 'encoder'],
        ['role' => 'Sales Rep', 'level' => 'view'],
        ['role' => 'Area Business Manager', 'level' => 'approver'],
        ['role' => 'Area Business Manager', 'level' => 'view'],
    ];

    public function up(): void
    {
        $module = DB::table('modules')->where('key', 'sales')->first();

        if (! $module) {
            return;
        }

        $now = now();

        foreach ($this->grants as $grant) {
            $role = DB::table('list_roles')->where('name', $grant['role'])->first();

            if (! $role) {
                continue;
            }

            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $role->id,
                    'module_id' => $module->id,
                    'submodule_id' => null,
                    'access_level' => $grant['level'],
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $module = DB::table('modules')->where('key', 'sales')->first();

        if (! $module) {
            return;
        }

        foreach ($this->grants as $grant) {
            $role = DB::table('list_roles')->where('name', $grant['role'])->first();

            if (! $role) {
                continue;
            }

            DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->where('module_id', $module->id)
                ->whereNull('submodule_id')
                ->where('access_level', $grant['level'])
                ->delete();
        }
    }
};
