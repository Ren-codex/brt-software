<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\System\Permission\PermissionService;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Database\Seeders\SeniorSalesRepRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Senior Sales Rep sees (and manages) every rep's sales on the order tabs,
 * but can only look at the team's remittances and cheques. "See all" is keyed
 * off admin on any sales submodule, so the order-tab admin grants give them the
 * whole team's view everywhere, while remittances/cheques stay view-only.
 */
class SeniorSalesRepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->seed(SeniorSalesRepRoleSeeder::class);
    }

    private function permissions(): PermissionService
    {
        return app(PermissionService::class);
    }

    private function seniorSalesRep(): User
    {
        $role = ListRole::where('name', 'Senior Sales Rep')->firstOrFail();
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        return $user->fresh();
    }

    public function test_a_senior_sales_rep_sees_every_reps_sales(): void
    {
        $this->assertNull($this->permissions()->salesScopeEmployeeId($this->seniorSalesRep()));
    }

    public function test_they_can_manage_the_order_tabs(): void
    {
        $user = $this->seniorSalesRep();

        foreach (['sales_orders', 'ar_invoices', 'receipts', 'sales_returns'] as $tab) {
            $this->assertTrue(
                $this->permissions()->userHasAccess($user, 'sales', $tab, 'admin'),
                "expected admin on {$tab}"
            );
        }
    }

    public function test_they_can_only_view_remittances_and_cheques(): void
    {
        $user = $this->seniorSalesRep();

        foreach (['remittances', 'check_monitoring'] as $tab) {
            $this->assertTrue(
                $this->permissions()->userHasAccess($user, 'sales', $tab, 'view'),
                "expected view on {$tab}"
            );
            $this->assertFalse(
                $this->permissions()->userHasAccess($user, 'sales', $tab, 'approver'),
                "{$tab} must not be approvable"
            );
            $this->assertFalse(
                $this->permissions()->userHasAccess($user, 'sales', $tab, 'admin'),
                "{$tab} must not be admin"
            );
        }
    }

    public function test_someone_without_the_grant_is_still_scoped(): void
    {
        $user = User::factory()->create();
        $this->assertNotNull($this->permissions()->salesScopeEmployeeId($user->fresh()));
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(SeniorSalesRepRoleSeeder::class);
        $this->seed(SeniorSalesRepRoleSeeder::class);

        $this->assertSame(1, ListRole::where('name', 'Senior Sales Rep')->count());

        $role = ListRole::where('name', 'Senior Sales Rep')->firstOrFail();
        $sales = Module::where('key', 'sales')->firstOrFail();
        // 4 manage + 2 view-only = 6 grants, no more.
        $this->assertSame(6, RolePermission::where('role_id', $role->id)->where('module_id', $sales->id)->count());
    }
}
