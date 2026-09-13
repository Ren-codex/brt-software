<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two surfaces over one register, deliberately in different modules.
 *
 * The owner's surface sits under Accounting, beside Bank Reconciliation and
 * Cash Management, because confirming that money reached the bank is an
 * accounting act. The rep's surface sits under Sales, because putting it in
 * Accounting would mean granting reps a module they have no business in —
 * Journal Entries and Chart of Accounts live there too.
 *
 * Separating them is also what makes "cleared" mean anything: a rep must not be
 * able to discharge their own check.
 *
 * Module-wide grants satisfy a submodule check, so Administrator and Accountant
 * keep the Accounting surface with no permission edits, and Sales Rep and Area
 * Business Manager pick up the Sales surface from the module-wide sales grants
 * they already hold. Row inserts only — no schema change.
 */
return new class extends Migration
{
    private const SUBMODULES = [
        ['module' => 'accounting', 'key' => 'check_register', 'name' => 'Check Register'],
        ['module' => 'sales', 'key' => 'check_monitoring', 'name' => 'Check Monitoring'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::SUBMODULES as $entry) {
            $module = DB::table('modules')->where('key', $entry['module'])->first();

            if (!$module || DB::table('submodules')->where('key', $entry['key'])->exists()) {
                continue;
            }

            DB::table('submodules')->insert([
                'module_id' => $module->id,
                'key' => $entry['key'],
                'name' => $entry['name'],
                'sort_order' => (int) DB::table('submodules')->where('module_id', $module->id)->max('sort_order') + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::SUBMODULES as $entry) {
            $submodule = DB::table('submodules')->where('key', $entry['key'])->first();

            if (!$submodule) {
                continue;
            }

            // Drop grants pointing at it first, or they would dangle.
            DB::table('role_permissions')->where('submodule_id', $submodule->id)->delete();
            DB::table('submodules')->where('id', $submodule->id)->delete();
        }
    }
};
