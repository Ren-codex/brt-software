<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\ArInvoice;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\Check;
use App\Models\JournalEntryLine;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A cheque is not cash, and depositing one must not say it was.
 *
 * Every deposit used to credit whichever cash account the form had selected,
 * and the form defaulted to Cash on Hand. So banking a customer's cheque took
 * money out of the drawer that was never in it, while the cheque itself stayed
 * sitting in collections in transit. Two wrongs of the same size: the trial
 * balance still balanced, and nothing looked broken, but Cash on Hand was
 * short by every cheque ever deposited and the transit account never cleared.
 *
 * A cheque deposit now draws on the account the cheque is actually in. The
 * collection debits transit, the deposit credits it, and the pair nets to
 * nothing — which is what moving a cheque from a drawer to a bank should do to
 * a cash balance.
 */
class ChequeDepositSourceTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);

        $this->bank = BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT', 'account_number' => '0001',
            'gl_code' => '1020', 'is_active' => 1,
        ]);
    }

    /**
     * The ledger account, created on demand exactly as the posting code does.
     * 1050 only exists once something has been collected, and a test that
     * asserts it is absent from a dropdown would otherwise pass by accident.
     */
    private function account(string $code): Account
    {
        $known = [
            '1000' => ['cash', 'Cash', 'current_asset'],
            '1050' => ['undeposited_collections', 'Undeposited Collections', 'current_asset'],
            '1100' => ['accounts_receivable', 'Accounts Receivable', 'current_asset'],
        ][$code];

        return Account::firstOrCreate(
            ['code' => $code],
            ['slug' => $known[0], 'name' => $known[1], 'type' => 'asset', 'subtype' => $known[2], 'is_active' => true]
        );
    }

    private function balance(string $code): float
    {
        $account = Account::where('code', $code)->first();
        if (! $account) {
            return 0.0;
        }

        return round(
            (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'debit')->sum('amount')
            - (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'credit')->sum('amount'),
            2
        );
    }

    /** Someone who may record deposits and clear cheques. */
    private function accountant(array $extraGrants = []): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        $grants = ['cash_management' => 'encoder', 'check_register' => 'approver'] + $extraGrants;
        foreach ($grants as $submodule => $level) {
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $module->submodules()->where('key', $submodule)->firstOrFail()->id,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    /** A customer pays by cheque: nothing is posted until the bank pays it. */
    private function chequeCollected(string $number = 'CHK-DEP'): Check
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $invoice = ArInvoice::firstOrFail();

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id, 'option' => 'payment', 'action' => 'payment',
            'payment_date' => now()->toDateString(), 'balance_due' => (float) $invoice->balance_due,
            'splits' => [[
                'payment_mode' => 'Check', 'amount' => 3000, 'bank_account_id' => null,
                'reference_number' => $number, 'check_date' => now()->addDays(3)->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        return Check::where('check_number', $number)->firstOrFail();
    }

    private function depositCheque(User $as, string $number = 'CHK-DEP', array $extra = [])
    {
        return $this->actingAs($as)->postJson('/accounting/bank-deposits', array_merge([
            'bank_account_id' => $this->bank->id,
            'amount' => 3000,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'check',
            'check_number' => $number,
            'check_date' => now()->addDays(3)->toDateString(),
        ], $extra));
    }

    public function test_banking_a_cheque_leaves_cash_on_hand_alone(): void
    {
        $accountant = $this->accountant();
        $cheque = $this->chequeCollected();

        $this->depositCheque($accountant)->assertOk();
        $this->actingAs($accountant)
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm', ['bank_name' => 'BDO'])
            ->assertOk();

        // The money moved from the customer to the bank, and touched no drawer
        // on the way: transit took it in and handed it straight out.
        $this->assertSame(0.0, $this->balance('1000'), 'Cash on Hand must not move for a cheque.');
        $this->assertSame(0.0, $this->balance('1050'), 'Transit must clear once the cheque is banked.');
        $this->assertSame(0.0, $this->balance('1100'), 'The customer no longer owes it.');
        $this->assertSame(3000.0, $this->balance('1020'), 'The bank has it.');
    }

    public function test_the_deposit_records_where_the_cheque_really_was(): void
    {
        $accountant = $this->accountant();
        $this->chequeCollected();

        // The form sends no cash account for a cheque; the server knows.
        $this->depositCheque($accountant)->assertOk();

        $deposit = BankDeposit::firstOrFail();
        $this->assertSame(
            Account::where('code', '1050')->value('id'),
            $deposit->cash_account_id
        );
    }

    public function test_a_cheque_deposit_is_not_held_to_the_cash_balance(): void
    {
        // There is no cash in the drawer at all. A cheque is still bankable:
        // it is the customer's money, not ours, and never was cash.
        $accountant = $this->accountant();
        $this->chequeCollected();
        $this->assertSame(0.0, $this->balance('1000'));

        $this->depositCheque($accountant)->assertOk();
    }

    public function test_a_cash_deposit_still_comes_out_of_the_drawer(): void
    {
        $accountant = $this->accountant(['journal_entries' => 'encoder']);
        $cash = $this->account('1000');

        // Put something in the drawer, the way the office would.
        $this->actingAs($accountant)->postJson('/accounting/journal-entries', [
            'entry_date' => now()->toDateString(),
            'memo' => 'Opening cash.',
            'lines' => [
                ['account_id' => $cash->id, 'line_type' => 'debit', 'amount' => 5000, 'description' => 'Cash in.'],
                ['account_id' => $this->account('1100')->id, 'line_type' => 'credit', 'amount' => 5000, 'description' => 'Balancing line.'],
            ],
        ])->assertSuccessful();

        $this->actingAs($accountant)->postJson('/accounting/bank-deposits', [
            'cash_account_id' => $cash->id,
            'bank_account_id' => $this->bank->id,
            'amount' => 2000,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'cash',
        ])->assertOk();

        $this->assertSame(3000.0, $this->balance('1000'));
        $this->assertSame(2000.0, $this->balance('1020'));
    }

    public function test_a_cash_deposit_still_needs_a_source(): void
    {
        $this->actingAs($this->accountant())->postJson('/accounting/bank-deposits', [
            'bank_account_id' => $this->bank->id,
            'amount' => 1000,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'cash',
        ])->assertStatus(422)->assertJsonValidationErrors('cash_account_id');
    }

    public function test_only_cash_on_hand_can_be_deposited_from(): void
    {
        // Both accounts exist, so this is a real choice being withheld and
        // not an empty table. Money leaves transit one way: by being handed to
        // the office and verified. Offering it here would be a second door out.
        $this->account('1000');
        $this->account('1050');

        $page = $this->actingAs($this->accountant())->get('/accounting/cash-management');
        $page->assertOk();

        $codes = collect($page->viewData('page')['props']['cashAccounts'] ?? [])
            ->pluck('code')->all();

        $this->assertSame(['1000'], $codes);
    }
}
