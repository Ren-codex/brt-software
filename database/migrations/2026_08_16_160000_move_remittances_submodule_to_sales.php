<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remittances is prepared and managed from the Sales tab, not Accounting —
 * but its permission gate (frontend can()/canAny() checks, route
 * middleware) was wired to the 'accounting' module's 'remittances'
 * submodule. Since only Administrator was ever granted accounting access,
 * Sales Rep (who has module-wide 'sales' encoder/view) could never see the
 * Remittances tab. Reassigning the existing submodule row's module_id (not
 * creating a new one) keeps its id stable so existing role_permissions
 * rows carry over unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accounting = DB::table('modules')->where('key', 'accounting')->first();
        $sales = DB::table('modules')->where('key', 'sales')->first();

        if (!$accounting || !$sales) {
            return;
        }

        DB::table('submodules')
            ->where('module_id', $accounting->id)
            ->where('key', 'remittances')
            ->update(['module_id' => $sales->id, 'sort_order' => 5]);
    }

    public function down(): void
    {
        $accounting = DB::table('modules')->where('key', 'accounting')->first();
        $sales = DB::table('modules')->where('key', 'sales')->first();

        if (!$accounting || !$sales) {
            return;
        }

        DB::table('submodules')
            ->where('module_id', $sales->id)
            ->where('key', 'remittances')
            ->update(['module_id' => $accounting->id, 'sort_order' => 8]);
    }
};
