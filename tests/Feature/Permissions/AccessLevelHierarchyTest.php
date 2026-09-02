<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reproduction: granting a working level (encoder/approver/void/releaser) on a
 * submodule should also let the holder open the page they work in. Routes gate
 * reads with `view`, so without a hierarchy an encoder is locked out entirely.
 */
class AccessLevelHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithGrant(string $moduleKey, ?string $submoduleKey, string $level): User
    {
        $user = User::factory()->create();
        $role = ListRole::create([
            'name' => 'Role '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', $moduleKey)->firstOrFail();
        $submoduleId = $submoduleKey
            ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id
            : null;

        RolePermission::create([
            'role_id'      => $role->id,
            'module_id'    => $module->id,
            'submodule_id' => $submoduleId,
            'access_level' => $level,
        ]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    public function test_encoder_can_open_the_page_they_encode_in(): void
    {
        $user = $this->userWithGrant('accounting', 'expenses', 'encoder');

        $this->actingAs($user)->get('/accounting/expenses')->assertOk();
    }

    public function test_approver_can_open_the_page_they_approve_in(): void
    {
        $user = $this->userWithGrant('accounting', 'petty_cash', 'approver');

        $this->actingAs($user)->get('/accounting/petty-cash')->assertOk();
    }

    public function test_void_can_open_the_page(): void
    {
        $user = $this->userWithGrant('accounting', 'petty_cash', 'void');

        $this->actingAs($user)->get('/accounting/petty-cash')->assertOk();
    }

    public function test_view_alone_still_cannot_encode(): void
    {
        $user = $this->userWithGrant('accounting', 'expenses', 'view');

        // Read is allowed...
        $this->actingAs($user)->get('/accounting/expenses')->assertOk();
        // ...but writing is not.
        $this->actingAs($user)->post('/accounting/expenses', [])->assertForbidden();
    }

    public function test_encoder_still_cannot_approve(): void
    {
        $user = $this->userWithGrant('accounting', 'expenses', 'encoder');

        $this->actingAs($user)->patch('/accounting/expenses/1/approve')->assertForbidden();
    }

    /**
     * The reported bug: accounting routes sat inside a role:Administrator group,
     * so RoleMiddleware aborted with 'Unauthorized' before the granular check
     * ran. Permissions assigned to any other role therefore did nothing.
     */
    public function test_non_administrator_with_accounting_grant_can_access(): void
    {
        $user = $this->userWithGrant('accounting', 'expenses', 'view');

        $this->assertFalse(
            $user->roles()->where('name', 'Administrator')->exists(),
            'This user must not be an Administrator, or the test proves nothing.'
        );

        // Reaching the page at all is the point: this used to 403 with
        // 'Unauthorized' from RoleMiddleware no matter what was granted.
        $this->actingAs($user)->get('/accounting/expenses')->assertOk();
    }

    /**
     * The dashboard and the AR/AP aging reports use MySQL's DATEDIFF, which
     * SQLite has no equivalent for, so they cannot be exercised here. Their
     * gating is covered by the other routes in this file.
     */
    public function test_financial_reports_grant_reaches_a_reports_route(): void
    {
        $user = $this->userWithGrant('accounting', 'financial_reports', 'view');

        // Not assertOk(): under SQLite this route dies on DATEDIFF. What
        // matters is that it is no longer rejected by the role gate.
        $this->actingAs($user)->get('/accounting')->assertStatus(500);

        $outsider = $this->userWithGrant('sales', 'sales_orders', 'view');
        $this->actingAs($outsider)->get('/accounting')->assertForbidden();
    }

    /** Removing the role gate must not make accounting open to everyone. */
    public function test_user_without_any_accounting_grant_is_still_denied(): void
    {
        $user = $this->userWithGrant('sales', 'sales_orders', 'view');

        $this->actingAs($user)->get('/accounting')->assertForbidden();
        $this->actingAs($user)->get('/accounting/expenses')->assertForbidden();
        $this->actingAs($user)->get('/accounting/petty-cash')->assertForbidden();
    }

    /**
     * These three had no granular guard, so they would have been left open to
     * any authenticated user once the role gate was removed.
     */
    public function test_previously_unguarded_routes_now_require_permission(): void
    {
        $outsider = $this->userWithGrant('sales', 'sales_orders', 'view');

        foreach (['/accounting/cash-on-hand', '/accounting/chart-of-accounts', '/accounting/bank-accounts'] as $uri) {
            $this->actingAs($outsider)->get($uri)->assertForbidden();
        }

        // And still reachable with the right accounting grant.
        $cash = $this->userWithGrant('accounting', 'cash_management', 'view');
        $this->actingAs($cash)->get('/accounting/cash-on-hand')->assertOk();

        $coa = $this->userWithGrant('accounting', 'chart_of_accounts', 'view');
        $this->actingAs($coa)->get('/accounting/chart-of-accounts')->assertRedirect('/accounting/settings');
    }

    /** A module-wide accounting grant still covers every submodule. */
    public function test_module_wide_grant_covers_submodules(): void
    {
        $user = $this->userWithGrant('accounting', null, 'admin');

        $this->actingAs($user)->get('/accounting/expenses')->assertOk();
        $this->actingAs($user)->get('/accounting/petty-cash')->assertOk();
    }
}
