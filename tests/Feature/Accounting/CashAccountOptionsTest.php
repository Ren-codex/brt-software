<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The cash source dropdown must offer cash on hand and nothing else. Subtype
 * cannot be used to decide that — the chart marks 1000 Cash as 'current_asset',
 * the same catch-all it gives Accounts Receivable and every bank GL — so these
 * pin the allowlist behaviour.
 */
class CashAccountOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWithCashManagementAccess(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        $submodule = $module->submodules()->where('key', 'cash_management')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submodule->id, 'access_level' => 'admin',
        ]);

        return $user;
    }

    private function account(string $code, string $slug, string $name, string $subtype = 'current_asset'): Account
    {
        return Account::firstOrCreate(
            ['slug' => $slug],
            ['code' => $code, 'name' => $name, 'type' => 'asset', 'subtype' => $subtype, 'is_active' => true]
        );
    }

    private function cashAccountSlugs(): array
    {
        $slugs = [];

        $this->actingAs($this->userWithCashManagementAccess())
            ->get('/accounting/cash-management')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$slugs) {
                $ids = collect($page->toArray()['props']['cashAccounts'] ?? [])->pluck('id');
                $slugs = Account::whereIn('id', $ids)->pluck('slug')->sort()->values()->all();
            });

        return $slugs;
    }

    public function test_cash_on_hand_accounts_are_offered(): void
    {
        $this->account('1000', 'cash', 'Cash');
        $this->account('1050', 'undeposited_collections', 'Undeposited Collections');

        $slugs = $this->cashAccountSlugs();

        $this->assertContains('cash', $slugs);
        $this->assertContains('undeposited_collections', $slugs);
    }

    public function test_bank_gl_accounts_are_never_offered_as_cash(): void
    {
        $this->account('1000', 'cash', 'Cash');
        BankAccount::firstOrCreate(['gl_code' => '1020'], ['bank_name' => 'BDO', 'account_name' => 'BRT']);
        $this->account('1020', 'bank_1020', 'BDO — BRT');

        $this->assertNotContains('bank_1020', $this->cashAccountSlugs());
    }

    public function test_non_cash_asset_accounts_are_not_offered(): void
    {
        $this->account('1000', 'cash', 'Cash');
        $this->account('1100', 'accounts_receivable', 'Accounts Receivable');
        $this->account('1160', 'employee_loans_receivable', 'Employee Loans Receivable');
        $this->account('1300', 'prepaid_expenses', 'Prepaid Expenses');
        $this->account('1090', 'card_clearing', 'Card Clearing');
        $this->account('1011', 'cash_in_bank', 'Cash in Bank', 'cash');

        $slugs = $this->cashAccountSlugs();

        foreach (['accounts_receivable', 'employee_loans_receivable', 'prepaid_expenses', 'card_clearing', 'cash_in_bank'] as $slug) {
            $this->assertNotContains($slug, $slugs);
        }
    }

    public function test_inactive_cash_accounts_are_not_offered(): void
    {
        $this->account('1000', 'cash', 'Cash');
        $collections = $this->account('1050', 'undeposited_collections', 'Undeposited Collections');
        $collections->update(['is_active' => false]);

        $this->assertNotContains('undeposited_collections', $this->cashAccountSlugs());
    }
}
