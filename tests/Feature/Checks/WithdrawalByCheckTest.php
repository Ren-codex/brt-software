<?php

namespace Tests\Feature\Checks;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankWithdrawal;
use App\Models\Check;
use App\Models\JournalEntry;
use App\Services\Accounting\CashManagementService;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Modules\CheckRegisterClass;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Withdrawing by writing a check to cash empties the account when the check is
 * presented, not when it is written. Posting it on the spot overstates cash on
 * hand, understates the bank, and hides the commitment from the forecast built
 * to warn about exactly that.
 */
class WithdrawalByCheckTest extends TestCase
{
    use RefreshDatabase;

    private function bank(): BankAccount
    {
        return BankAccount::firstOrCreate(['gl_code' => '1020'], ['bank_name' => 'BDO', 'account_name' => 'BRT']);
    }

    private function cash(): Account
    {
        return Account::firstOrCreate(
            ['slug' => 'cash'],
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset']
        );
    }

    private function withdraw(array $overrides = []): BankWithdrawal
    {
        return app(CashManagementService::class)->createBankWithdrawal(array_merge([
            'bank_account_id' => $this->bank()->id,
            'cash_account_id' => $this->cash()->id,
            'amount' => 500000,
            'withdrawal_date' => now()->toDateString(),
            'withdrawal_method' => 'slip',
        ], $overrides));
    }

    /** Debit the bank's GL account, the way a deposit into it would. */
    private function fundBank(float $amount): void
    {
        $gl = Account::firstOrCreate(
            ['code' => $this->bank()->gl_code],
            ['slug' => 'bank_' . $this->bank()->gl_code, 'name' => 'BDO', 'type' => 'asset', 'subtype' => 'current_asset']
        );

        $entry = \App\Models\JournalEntry::create([
            'journal_number' => 'JE-TEST-' . uniqid(),
            'entry_date' => now()->toDateString(),
            'entry_type' => 'manual',
            'source_type' => Account::class,
            'source_id' => $gl->id,
            'memo' => 'Opening balance for test',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $entry->lines()->create([
            'account_id' => $gl->id, 'line_type' => 'debit',
            'amount' => $amount, 'description' => 'Opening balance', 'line_order' => 1,
        ]);
    }

    private function entriesFor(BankWithdrawal $w): int
    {
        return JournalEntry::where('source_type', BankWithdrawal::class)->where('source_id', $w->id)->count();
    }

    public function test_a_slip_withdrawal_still_posts_immediately(): void
    {
        $withdrawal = $this->withdraw();

        $this->assertSame(BankWithdrawal::STATUS_POSTED, $withdrawal->status);
        $this->assertSame(1, $this->entriesFor($withdrawal));
    }

    public function test_a_check_withdrawal_posts_nothing_when_written(): void
    {
        $withdrawal = $this->withdraw([
            'withdrawal_method' => 'check',
            'check_number' => 'WD-0001',
            'check_date' => now()->addDays(9)->toDateString(),
        ]);

        $this->assertSame(BankWithdrawal::STATUS_PENDING, $withdrawal->status);
        $this->assertSame(0, $this->entriesFor($withdrawal), 'The cash is not in hand until the check is presented.');
    }

    public function test_a_check_withdrawal_lands_in_the_register(): void
    {
        $checkDate = now()->addDays(9)->toDateString();
        $withdrawal = $this->withdraw([
            'withdrawal_method' => 'check',
            'check_number' => 'WD-0002',
            'check_date' => $checkDate,
        ]);

        $check = Check::issued()->where('source_id', $withdrawal->id)->first();

        $this->assertNotNull($check, 'Otherwise the forecast cannot see it.');
        $this->assertSame(Check::STATUS_PENDING, $check->status);
        $this->assertSame($checkDate, $check->check_date->toDateString());
        $this->assertSame($this->bank()->id, $check->bank_account_id);
    }

    public function test_clearing_it_posts_the_withdrawal(): void
    {
        $withdrawal = $this->withdraw([
            'withdrawal_method' => 'check',
            'check_number' => 'WD-0003',
            'check_date' => now()->addDays(9)->toDateString(),
        ]);
        $check = Check::issued()->where('source_id', $withdrawal->id)->firstOrFail();

        app(CheckRegisterClass::class)->markCleared($check);

        $this->assertSame(BankWithdrawal::STATUS_POSTED, $withdrawal->fresh()->status);
        $this->assertSame(1, $this->entriesFor($withdrawal));
    }

    public function test_a_pending_check_is_not_spendable_twice(): void
    {
        // The raw ledger balance still shows money a written check has claimed.
        // Spending against it is how one check clears and the next bounces.
        $this->withdraw([
            'withdrawal_method' => 'check',
            'check_number' => 'WD-0005',
            'check_date' => now()->addDays(9)->toDateString(),
            'amount' => 500000,
        ]);

        $service = app(CashManagementService::class);
        $raw = $service->getBankAccountBalance($this->bank()->id);
        $available = $service->getAvailableBankBalance($this->bank()->id);

        $this->assertSame(round($raw - 500000, 2), $available);
    }

    /**
     * At the HTTP boundary, not the service. The controller passes
     * $request->validate([...]), which returns only the keys the rules declare,
     * so a field the rules omit is stripped before the service ever sees it —
     * and every service-level test above would still pass.
     */
    public function test_the_check_fields_survive_request_validation(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'cash_management')->firstOrFail()->id,
            'access_level' => 'admin',
        ]);

        // Put real money in the account, or the balance guard rejects the request
        // before it can tell us anything about the check fields.
        $this->fundBank(1000000);

        $checkDate = now()->addDays(9)->toDateString();

        $this->actingAs($user)->postJson('/accounting/bank-withdrawals', [
            'bank_account_id' => $this->bank()->id,
            'cash_account_id' => $this->cash()->id,
            'amount' => 1000,
            'withdrawal_date' => now()->toDateString(),
            'withdrawal_method' => 'check',
            'check_number' => 'WD-HTTP-1',
            'check_date' => $checkDate,
        ])->assertSuccessful();

        $check = Check::issued()->where('check_number', 'WD-HTTP-1')->first();

        $this->assertNotNull($check, 'The method must survive validate(), or every check becomes a slip.');
        $this->assertSame($checkDate, $check->check_date->toDateString());
    }

    public function test_a_check_withdrawal_needs_its_number_and_date(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->withdraw(['withdrawal_method' => 'check', 'check_number' => 'WD-0004']);
    }
}
