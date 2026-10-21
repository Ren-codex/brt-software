<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\ArInvoice;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\Check;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Banking cheques means picking the ones in your hand, not typing them out.
 *
 * A cheque deposit used to ask for a number, a date and an amount, with
 * nothing checking that any such cheque existed. Two slips of the pen and the
 * deposit belonged to no cheque at all — or, worse, matched somebody else's,
 * since the only link between the two was the number and the amount.
 *
 * Now the office ticks cheques off a list of what is actually on hand. Several
 * can go to the bank together, and each one keeps its own deposit, because the
 * bank pays them one at a time and any of them can bounce alone.
 */
class DepositChequesFromTheListTest extends TestCase
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

    private function accountant(): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        foreach (['cash_management' => 'encoder', 'check_register' => 'approver'] as $submodule => $level) {
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $module->submodules()->where('key', $submodule)->firstOrFail()->id,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    /** A customer pays an invoice by cheque; the cheque reaches the register. */
    private function chequeOnHand(string $number, float $amount = 3000): Check
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $invoice = ArInvoice::where('sales_order_id', SalesOrder::latest('id')->value('id'))->firstOrFail();

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id, 'option' => 'payment', 'action' => 'payment',
            'payment_date' => now()->toDateString(), 'balance_due' => (float) $invoice->balance_due,
            'splits' => [[
                'payment_mode' => 'Check', 'amount' => $amount, 'bank_account_id' => null,
                'reference_number' => $number, 'check_date' => now()->addDays(4)->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        // The newest: two customers' cheques can carry the same number, which
        // is the whole point of one of these tests.
        return Check::where('check_number', $number)->latest('id')->firstOrFail();
    }

    /** What the deposit screen offers to bank. */
    private function offered(User $as): array
    {
        $page = $this->actingAs($as)->get('/accounting/cash-management');
        $page->assertOk();

        return collect($page->viewData('page')['props']['undepositedChecks'] ?? [])
            ->pluck('check_number')->all();
    }

    private function bank(User $as, array $checkIds, array $extra = [])
    {
        return $this->actingAs($as)->postJson('/accounting/bank-deposits', array_merge([
            'bank_account_id' => $this->bank->id,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'check',
            'check_ids' => $checkIds,
            'reference' => 'SLIP-1',
        ], $extra));
    }

    public function test_the_list_offers_the_cheques_on_hand(): void
    {
        $this->chequeOnHand('CHK-A');
        $this->chequeOnHand('CHK-B');

        $this->assertSame(['CHK-A', 'CHK-B'], $this->offered($this->accountant()));
    }

    public function test_a_cleared_or_bounced_cheque_is_not_offered(): void
    {
        $accountant = $this->accountant();
        $cleared = $this->chequeOnHand('CHK-CLEARED');
        $this->chequeOnHand('CHK-ONHAND');

        $this->actingAs($accountant)
            ->putJson('/accounting/check-register/'.$cleared->id.'/confirm', ['bank_name' => 'BDO'])
            ->assertOk();

        $this->assertSame(['CHK-ONHAND'], $this->offered($accountant));
    }

    public function test_ticking_three_banks_three_and_keeps_them_apart(): void
    {
        $accountant = $this->accountant();
        $a = $this->chequeOnHand('CHK-A', 1000);
        $b = $this->chequeOnHand('CHK-B', 2000);
        $c = $this->chequeOnHand('CHK-C', 3000);

        $this->bank($accountant, [$a->id, $b->id, $c->id])->assertOk();

        // One trip to the bank, three deposits: the bank pays them one at a
        // time and any one of them can bounce on its own.
        $deposits = BankDeposit::orderBy('id')->get();
        $this->assertCount(3, $deposits);
        $this->assertSame([1000.0, 2000.0, 3000.0], $deposits->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertSame([$a->id, $b->id, $c->id], $deposits->pluck('check_id')->all());
        // Same slip, same day, same bank.
        $this->assertSame(['SLIP-1'], $deposits->pluck('reference')->unique()->values()->all());
        $this->assertSame([$this->bank->id], $deposits->pluck('bank_account_id')->unique()->values()->all());
    }

    public function test_the_amount_comes_from_the_cheque_not_the_caller(): void
    {
        $accountant = $this->accountant();
        $cheque = $this->chequeOnHand('CHK-A', 1000);

        // A caller naming its own amount is ignored: the cheque says what it
        // is worth, and the bank will pay that and nothing else.
        $this->bank($accountant, [$cheque->id], ['amount' => 999999])->assertOk();

        $this->assertSame(1000.0, (float) BankDeposit::firstOrFail()->amount);
    }

    public function test_a_banked_cheque_leaves_the_list(): void
    {
        $accountant = $this->accountant();
        $a = $this->chequeOnHand('CHK-A');
        $this->chequeOnHand('CHK-B');

        $this->bank($accountant, [$a->id])->assertOk();

        $this->assertSame(['CHK-B'], $this->offered($accountant));
    }

    public function test_the_same_cheque_cannot_be_banked_twice(): void
    {
        $accountant = $this->accountant();
        $cheque = $this->chequeOnHand('CHK-A');

        $this->bank($accountant, [$cheque->id])->assertOk();
        $this->bank($accountant, [$cheque->id])->assertStatus(422);

        $this->assertSame(1, BankDeposit::count());
    }

    public function test_clearing_one_posts_only_its_own_deposit(): void
    {
        $accountant = $this->accountant();
        $a = $this->chequeOnHand('CHK-A', 1000);
        $b = $this->chequeOnHand('CHK-B', 2000);
        $this->bank($accountant, [$a->id, $b->id])->assertOk();

        $this->actingAs($accountant)
            ->putJson('/accounting/check-register/'.$a->id.'/confirm', ['bank_name' => 'BDO'])
            ->assertOk();

        $this->assertSame(BankDeposit::STATUS_POSTED, BankDeposit::where('check_id', $a->id)->firstOrFail()->status);
        $this->assertSame(BankDeposit::STATUS_PENDING, BankDeposit::where('check_id', $b->id)->firstOrFail()->status);

        // And only that cheque's money is in the bank.
        $bankGl = Account::where('code', '1020')->firstOrFail();
        $balance = round((float) \App\Models\JournalEntryLine::where('account_id', $bankGl->id)->where('line_type', 'debit')->sum('amount')
            - (float) \App\Models\JournalEntryLine::where('account_id', $bankGl->id)->where('line_type', 'credit')->sum('amount'), 2);
        $this->assertSame(1000.0, $balance);
    }

    public function test_two_cheques_sharing_a_number_no_longer_jam_each_other(): void
    {
        // Different customers, different banks, same printed number — the case
        // the old number-matching refused to post at all.
        $accountant = $this->accountant();
        $first = $this->chequeOnHand('0012345', 1000);
        $second = $this->chequeOnHand('0012345', 1000);
        $this->assertNotSame($first->id, $second->id, 'Two separate cheques, one printed number.');

        $this->bank($accountant, [$first->id, $second->id])->assertOk();

        $this->actingAs($accountant)
            ->putJson('/accounting/check-register/'.$first->id.'/confirm', ['bank_name' => 'BDO'])
            ->assertOk();

        $this->assertSame(BankDeposit::STATUS_POSTED, BankDeposit::where('check_id', $first->id)->firstOrFail()->status);
        $this->assertSame(BankDeposit::STATUS_PENDING, BankDeposit::where('check_id', $second->id)->firstOrFail()->status);
    }

    public function test_a_cheque_deposit_still_needs_a_cheque(): void
    {
        $this->bank($this->accountant(), [])->assertStatus(422)->assertJsonValidationErrors('check_ids');
    }
}
