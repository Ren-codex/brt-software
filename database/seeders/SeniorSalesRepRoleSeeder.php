<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

/**
 * A "Senior Sales Rep" — a sales rep who answers for the whole team, not just
 * their own book.
 *
 *   - `admin` on the order-side tabs (sales orders, AR invoices, receipts,
 *     sales returns): they can view AND manage every rep's, not only their own.
 *     Admin on any sales submodule is what makes sales visibility unrestricted
 *     (see PermissionService::salesScopeEmployeeId), so they see the whole
 *     team's sales across every tab.
 *   - `view` only on Remittances and Check Monitoring: they can see all of the
 *     team's remittances and cheques, but cannot verify/approve a remittance or
 *     confirm/bounce a cheque.
 *
 * The sales module is permission-gated (canAny('sales'), permission:sales
 * routes), so no role-name wiring is needed — the grants alone make it work.
 *
 * Idempotent: it resets this role's sales grants to the set below each run, so
 * running it again always lands on the intended shape.
 */
class SeniorSalesRepRoleSeeder extends Seeder
{
    /** Submodules the senior rep can manage across the whole team. */
    private const MANAGE = ['sales_orders', 'ar_invoices', 'receipts', 'sales_returns'];

    /** Submodules the senior rep can only look at. */
    private const VIEW_ONLY = ['remittances', 'check_monitoring'];

    public function run(): void
    {
        $role = ListRole::firstOrCreate(
            ['name' => 'Senior Sales Rep'],
            [
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'A senior sales representative who can view and manage every rep\'s sales orders, invoices, receipts and returns, and view (but not act on) the team\'s remittances and cheques.',
            ]
        );

        $sales = Module::where('key', 'sales')->firstOrFail();

        // Define this role's sales grants afresh so re-running is exact.
        RolePermission::where('role_id', $role->id)->where('module_id', $sales->id)->delete();

        $grant = function (string $submoduleKey, string $level) use ($role, $sales): void {
            $submodule = $sales->submodules()->where('key', $submoduleKey)->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id,
                'module_id' => $sales->id,
                'submodule_id' => $submodule->id,
                'access_level' => $level,
            ]);
        };

        foreach (self::MANAGE as $submoduleKey) {
            $grant($submoduleKey, 'admin');
        }

        foreach (self::VIEW_ONLY as $submoduleKey) {
            $grant($submoduleKey, 'view');
        }
    }
}
