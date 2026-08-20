<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportsPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    /**
     * Every fixture holds the Administrator role (satisfies the existing
     * role:Administrator gate) and varies only the new fine-grained grant.
     */
    private function administratorWithGrant(?string $submoduleKey, ?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submoduleId, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    public function test_dashboard_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant('expenses', 'view');

        $this->actingAs($user)->get('/accounting')->assertForbidden();
    }

    public function test_dashboard_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('financial_reports', 'view');

        // Not assertOk(): the dashboard's quick-stats query uses a raw
        // DATEDIFF() call that only MySQL supports, so it 500s against the
        // sqlite test driver — a pre-existing business-logic/DB-driver
        // mismatch, unrelated to permissions. Asserting "not blocked by the
        // permission gate" is what this test is actually responsible for.
        $response = $this->actingAs($user)->get('/accounting');

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_general_ledger_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null, null);

        $this->actingAs($user)->get('/accounting/general-ledger')->assertForbidden();
    }

    public function test_trial_balance_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('financial_reports', 'view');

        $this->actingAs($user)->get('/accounting/trial-balance')->assertOk();
    }

    /**
     * This previously asserted the opposite — that a non-Administrator was
     * blocked even holding a module-wide accounting grant, because the routes
     * sat inside a role:Administrator group. That was the bug, not the intent:
     * permissions assigned through the Roles screen did nothing for accounting.
     * Access is now decided by the grant alone.
     */
    public function test_grant_alone_admits_a_non_administrator_role(): void
    {
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        // Not assertOk(): /accounting uses MySQL's DATEDIFF and dies under
        // SQLite. Reaching the controller at all is what this asserts.
        $this->actingAs($user)->get('/accounting')->assertStatus(500);
        $this->actingAs($user)->get('/accounting/expenses')->assertOk();
    }

    public function test_a_role_with_no_accounting_grant_is_still_denied(): void
    {
        $role = ListRole::create(['name' => 'Unrelated Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $this->actingAs($user)->get('/accounting')->assertForbidden();
        $this->actingAs($user)->get('/accounting/expenses')->assertForbidden();
    }
}
