<?php

namespace Tests\Feature\Payroll;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Payroll;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollProcessingPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
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
            $module = Module::where('key', 'payroll')->firstOrFail();
            $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submoduleId, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makePayroll(): Payroll
    {
        return Payroll::create([
            'payroll_no' => 'PR-TEST-' . uniqid(),
            'pay_period_start' => now()->toDateString(),
            'pay_period_end' => now()->addDays(14)->toDateString(),
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant('loans', 'view');

        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('payroll_processing', 'view');

        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('payroll_processing', 'view');

        $this->actingAs($user)->post('/payrolls', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('payroll_processing', 'encoder');

        $response = $this->actingAs($user)->post('/payrolls', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_update_status_denied_without_approver_grant(): void
    {
        $payroll = $this->makePayroll();
        $user = $this->administratorWithGrant('payroll_processing', 'encoder');

        $this->actingAs($user)->put("/payrolls/{$payroll->id}/status")->assertForbidden();
    }

    public function test_update_status_allowed_with_approver_grant(): void
    {
        $payroll = $this->makePayroll();
        $user = $this->administratorWithGrant('payroll_processing', 'approver');

        $response = $this->actingAs($user)->put("/payrolls/{$payroll->id}/status");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $payroll = $this->makePayroll();
        $user = $this->administratorWithGrant('payroll_processing', 'approver');

        $this->actingAs($user)->delete("/payrolls/{$payroll->id}")->assertForbidden();
    }

    /**
     * This previously asserted the opposite — that the holder was blocked even
     * with the right grant, because the routes sat inside a role:* group. That
     * was the bug: permissions assigned through the Roles screen did nothing.
     * Access is decided by the grant now.
     */
    public function test_grant_alone_admits_a_non_administrator_role(): void
    {
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'payroll')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertOk();
    }

    /**
     * The page shell and the payroll list share one action. Gating the whole
     * route on payroll_processing hid the page from a holder whose only grant
     * was Loans, even though the Loans tab lives on it — so the shell answers to
     * any payroll grant while the list keeps its own.
     */
    public function test_page_shell_opens_for_a_holder_of_any_payroll_grant(): void
    {
        $user = $this->administratorWithGrant('loans', 'view');

        $this->actingAs($user)->get('/payrolls')->assertOk();
    }

    public function test_page_shell_still_withholds_the_payroll_list_from_that_holder(): void
    {
        $user = $this->administratorWithGrant('loans', 'view');

        $this->actingAs($user)->get('/payrolls')->assertOk();
        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertForbidden();
    }

    public function test_page_shell_denied_without_any_payroll_grant(): void
    {
        $user = $this->administratorWithGrant(null, null);

        $this->actingAs($user)->get('/payrolls')->assertForbidden();
    }

    public function test_page_shell_opens_on_a_module_wide_grant(): void
    {
        $user = $this->administratorWithGrant(null, 'view');

        $this->actingAs($user)->get('/payrolls')->assertOk();
    }

    public function test_a_role_with_no_grant_for_this_module_is_still_denied(): void
    {
        $role = ListRole::create(['name' => 'Unrelated Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertForbidden();
    }
}
