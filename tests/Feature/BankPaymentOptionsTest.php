<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment screens live in Inventory and Sales, not only Accounting, so the list
 * of bank accounts they offer cannot be gated behind an accounting grant. It
 * was, which left a warehouse manager paying a supplier — and a sales rep
 * collecting from a customer — with an empty bank dropdown and no way to record
 * a transfer.
 */
class BankPaymentOptionsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/bank-accounts/payment-options';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT Operating', 'account_number' => '001',
            'gl_code' => '1020', 'balance' => 5000, 'is_active' => 1,
        ]);
    }

    private function userWith(string $module, ?string $submodule, string $level): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $mod = Module::where('key', $module)->firstOrFail();

        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $mod->id,
            'submodule_id' => $submodule ? $mod->submodules()->where('key', $submodule)->firstOrFail()->id : null,
            'access_level' => $level,
        ]);

        return $user;
    }

    public function test_a_warehouse_manager_receiving_stock_can_list_bank_accounts(): void
    {
        $user = $this->userWith('inventory', 'receiving', 'encoder');

        $response = $this->actingAs($user)->getJson(self::URL);

        $response->assertOk()->assertJsonCount(1);
        $this->assertEquals('BDO', $response->json('0.bank_name'));
    }

    public function test_a_sales_rep_taking_an_order_can_list_bank_accounts(): void
    {
        $user = $this->userWith('sales', 'sales_orders', 'encoder');

        $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonCount(1);
    }

    public function test_someone_collecting_against_an_invoice_can_list_bank_accounts(): void
    {
        $user = $this->userWith('sales', 'ar_invoices', 'encoder');

        $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonCount(1);
    }

    public function test_accounting_access_alone_still_works(): void
    {
        $user = $this->userWith('accounting', 'chart_of_accounts', 'view');

        $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonCount(1);
    }

    public function test_someone_who_cannot_record_payments_is_refused(): void
    {
        $user = $this->userWith('payroll', 'payroll_processing', 'view');

        $this->actingAs($user)->getJson(self::URL)->assertForbidden();
    }

    public function test_a_guest_is_refused(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_a_payer_is_told_the_balance_because_it_caps_what_they_can_spend(): void
    {
        $user = $this->userWith('inventory', 'receiving', 'encoder');

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()
            ->assertJsonStructure([['id', 'bank_name', 'account_name', 'balance']]);
    }

    public function test_someone_who_only_collects_is_not_told_the_balance(): void
    {
        $user = $this->userWith('sales', 'sales_orders', 'encoder');

        $body = $this->actingAs($user)->getJson(self::URL)->assertOk()->json();

        $this->assertArrayNotHasKey('balance', $body[0]);
    }

    public function test_account_numbers_and_gl_codes_are_never_exposed_here(): void
    {
        $user = $this->userWith('inventory', 'receiving', 'encoder');

        $body = $this->actingAs($user)->getJson(self::URL)->assertOk()->json();

        $this->assertArrayNotHasKey('account_number', $body[0]);
        $this->assertArrayNotHasKey('gl_code', $body[0]);
    }

    public function test_inactive_bank_accounts_are_not_offered(): void
    {
        BankAccount::create([
            'bank_name' => 'Closed Bank', 'account_name' => 'Old', 'account_number' => '999',
            'gl_code' => '1099', 'balance' => 0, 'is_active' => 0,
        ]);

        $user = $this->userWith('inventory', 'receiving', 'encoder');

        $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonCount(1);
    }
}
