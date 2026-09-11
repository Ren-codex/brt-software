<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for: Sales Rep could not save a Sales Return.
 *
 * Root cause: SalesDefaultPermissionsSeeder grants Sales Rep a module-wide
 * 'sales'/encoder RolePermission row, but it was only ever invoked from
 * DatabaseSeeder — never a migration — so already-migrated environments
 * never received it. SalesOrderRequest::authorize() requires
 * 'sales.sales_orders.encoder' for the 'adjustment' action (a sales return
 * save), and a module-wide grant (submodule_id = null) satisfies that check.
 * Fixed by migration 2026_09_09_000001_grant_sales_default_permissions.
 */
class SalesReturnPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'for-payment'], [
            'name' => 'For Payment', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
    }

    private function makeOrder(): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => 'SO-TEST-'.uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
    }

    public function test_sales_rep_without_the_grant_is_forbidden_from_saving_a_return(): void
    {
        $role = ListRole::create(['name' => 'Sales Rep', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $order = $this->makeOrder();

        $this->actingAs($user)
            ->put("/sales-orders/{$order->id}", ['action' => 'adjustment', 'type' => 'return', 'reason' => 'test'])
            ->assertForbidden();
    }

    public function test_sales_rep_with_the_module_wide_grant_can_save_a_return(): void
    {
        $role = ListRole::create(['name' => 'Sales Rep', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => null, 'access_level' => 'encoder',
        ]);

        $order = $this->makeOrder();

        $response = $this->actingAs($user)
            ->put("/sales-orders/{$order->id}", ['action' => 'adjustment', 'type' => 'return', 'reason' => 'test']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_migration_grants_sales_rep_the_module_wide_permission_when_role_already_exists(): void
    {
        // Simulate an already-seeded production DB: the role exists BEFORE
        // this migration runs (unlike RefreshDatabase's fresh-schema run,
        // where list_roles is still empty at migration time).
        ListRole::create(['name' => 'Sales Rep', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        ListRole::create(['name' => 'Area Business Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $migration = require database_path('migrations/2026_09_09_000001_grant_sales_default_permissions.php');
        $migration->up();

        $salesRep = ListRole::where('name', 'Sales Rep')->firstOrFail();
        $levels = RolePermission::where('role_id', $salesRep->id)
            ->whereNull('submodule_id')
            ->pluck('access_level')->sort()->values()->all();

        $this->assertEquals(['encoder', 'view'], $levels);

        // Idempotent: running it again must not duplicate rows.
        $migration->up();
        $this->assertEquals(2, RolePermission::where('role_id', $salesRep->id)->whereNull('submodule_id')->count());
    }
}
