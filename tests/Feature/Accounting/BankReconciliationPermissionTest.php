<?php

namespace Tests\Feature\Accounting;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'bank_reconciliation')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeReconciliation(): BankReconciliation
    {
        $bankAccount = BankAccount::create(['bank_name' => 'Bank A', 'account_name' => 'Acct A', 'gl_code' => 'GL-' . uniqid()]);

        return BankReconciliation::create([
            'bank_account_id' => $bankAccount->id, 'period_end' => now()->toDateString(), 'statement_balance' => 1000,
        ]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/bank-reconciliation')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/bank-reconciliation')->assertOk();
    }

    public function test_start_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-reconciliation', [])->assertForbidden();
    }

    public function test_toggle_clear_denied_without_encoder_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post("/accounting/bank-reconciliation/{$reconciliation->id}/toggle-clear")->assertForbidden();
    }

    public function test_finalize_denied_without_approver_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->post("/accounting/bank-reconciliation/{$reconciliation->id}/finalize")->assertForbidden();
    }

    public function test_finalize_allowed_with_approver_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->post("/accounting/bank-reconciliation/{$reconciliation->id}/finalize");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('approver');

        $this->actingAs($user)->delete("/accounting/bank-reconciliation/{$reconciliation->id}")->assertForbidden();
    }
}
