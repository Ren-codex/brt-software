<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\PettyCashFund;
use App\Models\ReplenishmentRequest;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A replenishment approved without a funding source used to fall back to a
 * generic "Cash in Bank" (1011) catch-all that is never deposited into, so it
 * drifted negative and split bank reporting away from the real per-bank
 * accounts. Approval now requires the approver to say where the money came from.
 */
class ReplenishmentFundingSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->user->id]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        foreach (['petty_cash'] as $key) {
            $submodule = $module->submodules()->where('key', $key)->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => 'admin',
            ]);
        }

        $this->actingAs($this->user);
    }

    /**
     * Approval refuses to draw from a source that has no money, so give the
     * target GL account an opening balance first.
     */
    private function fundAccount(string $code, string $name, float $amount): Account
    {
        $account = Account::firstOrCreate(
            ['code' => $code],
            ['slug' => 'acct-'.$code, 'name' => $name, 'type' => 'asset', 'subtype' => 'current_asset', 'is_active' => true]
        );
        $equity = Account::firstOrCreate(
            ['code' => '3900'],
            ['slug' => 'opening-balance-equity', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'subtype' => 'opening_balance', 'is_active' => true]
        );

        $entry = JournalEntry::create([
            'journal_number' => 'JE-TEST-'.uniqid(),
            'entry_date'     => now()->toDateString(),
            'entry_type'     => 'manual',
            'status'         => 'posted',
            'posted_at'      => now(),
        ]);
        \App\Models\JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $account->id, 'line_type' => 'debit', 'amount' => $amount, 'line_order' => 1]);
        \App\Models\JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $equity->id, 'line_type' => 'credit', 'amount' => $amount, 'line_order' => 2]);

        return $account;
    }

    private function makeSubmittedRequest(): ReplenishmentRequest
    {
        $fund = PettyCashFund::create([
            'name' => 'Main office', 'gl_code' => 'PCF-'.uniqid(), 'balance' => 1000, 'is_active' => true,
        ]);

        $replenishment = ReplenishmentRequest::create([
            'fund_id'       => $fund->id,
            'reference_no'  => 'REP-'.uniqid(),
            'total_amount'  => 8000,
            'expense_count' => 1,
            'status'        => 'submitted',
            'created_by_id' => $this->user->id,
        ]);

        Expense::create([
            'fund_id'                  => $fund->id,
            'expense_type'             => 'operational',
            'amount'                   => 8000,
            'expense_date'             => now()->toDateString(),
            'status'                   => 'submitted',
            'added_by_id'              => $this->user->id,
            'replenishment_request_id' => $replenishment->id,
        ]);

        return $replenishment;
    }

    public function test_approval_is_rejected_without_a_funding_source(): void
    {
        $replenishment = $this->makeSubmittedRequest();

        $this->patchJson("/replenishments/{$replenishment->id}/approve", ['review_notes' => 'ok'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_type');

        $this->assertSame('submitted', $replenishment->fresh()->status);
        $this->assertSame(0, JournalEntry::where('entry_type', 'petty_cash_replenishment')->count());
    }

    public function test_bank_source_requires_a_bank_account(): void
    {
        $replenishment = $this->makeSubmittedRequest();

        $this->patchJson("/replenishments/{$replenishment->id}/approve", ['source_type' => 'bank'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bank_account_id');
    }

    public function test_cash_source_credits_cash_on_hand_not_cash_in_bank(): void
    {
        $replenishment = $this->makeSubmittedRequest();
        $this->fundAccount('1000', 'Cash', 50000);

        $this->patchJson("/replenishments/{$replenishment->id}/approve", ['source_type' => 'cash'])
            ->assertOk();

        $entry = JournalEntry::where('entry_type', 'petty_cash_replenishment')->latest('id')->firstOrFail();
        $credit = $entry->lines->firstWhere('line_type', 'credit');

        $this->assertSame('1000', $credit->account->code, 'Cash source should credit Cash on hand.');

        // The account may exist from the chart of accounts, but nothing should
        // ever be posted against the generic catch-all by this flow.
        $catchAll = Account::where('code', '1011')->first();
        if ($catchAll) {
            $this->assertSame(
                0,
                \App\Models\JournalEntryLine::where('account_id', $catchAll->id)->count(),
                'Nothing should post to the generic Cash in Bank catch-all.'
            );
        }
    }

    public function test_bank_source_credits_the_specific_bank_account(): void
    {
        $replenishment = $this->makeSubmittedRequest();
        $bank = BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT', 'account_number' => '12345',
            'gl_code' => '1020', 'is_active' => true,
        ]);
        $this->fundAccount('1020', 'BDO — BRT', 50000);

        $this->patchJson("/replenishments/{$replenishment->id}/approve", [
            'source_type' => 'bank', 'bank_account_id' => $bank->id,
        ])->assertOk();

        $entry = JournalEntry::where('entry_type', 'petty_cash_replenishment')->latest('id')->firstOrFail();
        $credit = $entry->lines->firstWhere('line_type', 'credit');

        $this->assertSame('1020', $credit->account->code, 'Bank source should credit the specific bank GL, not 1011.');
    }
}
