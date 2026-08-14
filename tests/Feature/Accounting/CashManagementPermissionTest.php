<?php

namespace Tests\Feature\Accounting;

use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashManagementPermissionTest extends TestCase
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
            $submodule = $module->submodules()->where('key', 'cash_management')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeFundTransfer(): FundTransfer
    {
        $from = BankAccount::create(['bank_name' => 'Bank A', 'account_name' => 'Acct A', 'gl_code' => 'GL-' . uniqid()]);
        $to = BankAccount::create(['bank_name' => 'Bank B', 'account_name' => 'Acct B', 'gl_code' => 'GL-' . uniqid()]);

        return FundTransfer::create([
            'transfer_no' => 'FT-' . uniqid(), 'transfer_date' => now()->toDateString(),
            'from_bank_account_id' => $from->id, 'to_bank_account_id' => $to->id, 'amount' => 1000,
        ]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/cash-management')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/cash-management')->assertOk();
    }

    public function test_store_fund_transfer_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/fund-transfers', [])->assertForbidden();
    }

    public function test_store_fund_transfer_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/accounting/fund-transfers', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_fund_transfer_denied_without_admin_grant(): void
    {
        $transfer = $this->makeFundTransfer();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/accounting/fund-transfers/{$transfer->id}")->assertForbidden();
    }

    public function test_store_deposit_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-deposits', [])->assertForbidden();
    }

    public function test_store_withdrawal_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-withdrawals', [])->assertForbidden();
    }
}
