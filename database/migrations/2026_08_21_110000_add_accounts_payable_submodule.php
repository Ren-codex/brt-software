<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Separates settling a supplier bill from taking the goods in.
 *
 * Receiving and paying shared one permission (inventory/receiving/encoder), so
 * a warehouse manager could not be allowed to receive a delivery without also
 * being allowed to move money — which is why they needed sight of the bank
 * accounts at all. Paying now answers to accounting/accounts_payable instead.
 *
 * Placed under Accounting rather than Inventory deliberately: Administrator and
 * Accountant already hold module-wide accounting grants, and a module-wide
 * grant satisfies a submodule check, so both keep the ability with no
 * permission edits. Warehouse Manager holds inventory only and is excluded by
 * construction.
 */
return new class extends Migration
{
    private const KEY = 'accounts_payable';

    public function up(): void
    {
        $module = DB::table('modules')->where('key', 'accounting')->first();

        if (!$module) {
            return; // modules catalog not seeded here — nothing to attach to.
        }

        if (DB::table('submodules')->where('key', self::KEY)->exists()) {
            return;
        }

        $now = now();

        DB::table('submodules')->insert([
            'module_id'  => $module->id,
            'key'        => self::KEY,
            'name'       => 'Accounts Payable',
            'sort_order' => (int) DB::table('submodules')->where('module_id', $module->id)->max('sort_order') + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $submodule = DB::table('submodules')->where('key', self::KEY)->first();

        if (!$submodule) {
            return;
        }

        // Drop any grants pointing at it first, or they would dangle.
        DB::table('role_permissions')->where('submodule_id', $submodule->id)->delete();
        DB::table('submodules')->where('id', $submodule->id)->delete();
    }
};
