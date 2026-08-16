<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 2026_08_16_160000 migration moved the 'remittances' submodule from
 * 'accounting' to 'sales' so Sales Rep (module-wide 'sales' access) could
 * see it. But Accountant only ever had module-wide 'accounting' access
 * (encoder/approver/view) - with remittances no longer under that module,
 * Accountant lost all access to it, including the "Undeposited Cash"
 * dashboard on the Remittances page it needs to know what's ready to
 * deposit. Grant Accountant the same three levels directly on the
 * 'sales'/'remittances' submodule so their access is unchanged in effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('list_roles')->where('name', 'Accountant')->first();
        $submodule = DB::table('submodules')->where('key', 'remittances')->first();

        if (!$role || !$submodule) {
            return;
        }

        $now = now();

        foreach (['encoder', 'approver', 'view'] as $level) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $role->id,
                    'module_id' => $submodule->module_id,
                    'submodule_id' => $submodule->id,
                    'access_level' => $level,
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
        $role = DB::table('list_roles')->where('name', 'Accountant')->first();
        $submodule = DB::table('submodules')->where('key', 'remittances')->first();

        if (!$role || !$submodule) {
            return;
        }

        DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->where('submodule_id', $submodule->id)
            ->whereIn('access_level', ['encoder', 'approver', 'view'])
            ->delete();
    }
};
