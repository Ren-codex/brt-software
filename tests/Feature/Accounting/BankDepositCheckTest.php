<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\JournalEntry;
use App\Services\Accounting\CashManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A check deposit is not money until the check date. These cover the seam
 * between recording the deposit and posting its journal entry.
 */
class BankDepositCheckTest extends TestCase
{
    use RefreshDatabase;

    private function cashAccount(): Account
    {
        return Account::firstOrCreate(
            ['slug' => 'cash'],
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset']
        );
    }

    private function bankAccount(): BankAccount
    {
        return BankAccount::firstOrCreate(
            ['gl_code' => '1011'],
            ['bank_name' => 'Test Bank', 'account_name' => 'Operating']
        );
    }

    private function service(): CashManagementService
    {
        return app(CashManagementService::class);
    }

    private function entriesFor(BankDeposit $deposit)
    {
        return JournalEntry::where('source_type', BankDeposit::class)
            ->where('source_id', $deposit->id)
            ->get();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cash_account_id' => $this->cashAccount()->id,
            'bank_account_id' => $this->bankAccount()->id,
            'amount' => 5000,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'cash',
        ], $overrides);
    }

    /**
     * A cheque on hand, and the payload that banks it.
     *
     * Cheques are picked off the register rather than typed out, so a deposit
     * needs one to exist — and it is the cheque, not the caller, that says what
     * the deposit is worth and when it falls due.
     */
    private function chequePayload(string $number, string $checkDate, float $amount = 5000): array
    {
        $cheque = \App\Models\Check::create([
            'direction' => \App\Models\Check::DIRECTION_RECEIVED,
            'check_number' => $number,
            'check_date' => $checkDate,
            'amount' => $amount,
            'status' => \App\Models\Check::STATUS_PENDING,
            'source_type' => \App\Models\Receipt::class,
            'source_id' => random_int(10000, 99999),
        ]);

        return [
            'bank_account_id' => $this->bankAccount()->id,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'check',
            'check_ids' => [$cheque->id],
        ];
    }

    public function test_cash_deposit_posts_its_journal_entry_immediately(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload());

        $this->assertSame(BankDeposit::STATUS_POSTED, $deposit->status);
        $this->assertNotNull($deposit->posted_at);
        $this->assertCount(1, $this->entriesFor($deposit));
    }

    public function test_future_dated_check_creates_no_journal_entry(): void
    {
        $deposit = $this->service()->createBankDeposit(
            $this->chequePayload('CHK-001', now()->addDays(10)->toDateString())
        );

        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->status);
        $this->assertNull($deposit->posted_at);
        $this->assertCount(0, $this->entriesFor($deposit));
    }

    public function test_check_dated_today_is_created_pending_and_posts_nothing(): void
    {
        $deposit = $this->service()->createBankDeposit(
            $this->chequePayload('CHK-002', now()->toDateString())
        );

        // A check dated today can still bounce — the date on the check is
        // not proof it cleared, so it is never posted on creation, only via
        // a person confirming it via CheckRegisterClass::markCleared().
        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->status);
        $this->assertNull($deposit->posted_at);
        $this->assertCount(0, $this->entriesFor($deposit));
    }

    public function test_the_command_never_posts_a_due_check(): void
    {
        $deposit = $this->service()->createBankDeposit(
            $this->chequePayload('CHK-003', now()->addDays(3)->toDateString())
        );

        $this->travelTo(now()->addDays(3));
        $this->artisan('deposits:post-due-checks')->assertSuccessful();

        $deposit->refresh();
        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->status, 'Only a person confirms money arrived.');
        $this->assertCount(0, $this->entriesFor($deposit));
    }

    public function test_command_does_not_post_a_check_before_its_date(): void
    {
        $deposit = $this->service()->createBankDeposit(
            $this->chequePayload('CHK-004', now()->addDays(5)->toDateString())
        );

        $this->artisan('deposits:post-due-checks')->assertSuccessful();

        $deposit->refresh();
        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->status);
        $this->assertCount(0, $this->entriesFor($deposit));
    }

    public function test_a_pending_check_is_held_against_the_account_it_came_from(): void
    {
        $cash = $this->cashAccount();
        $deposit = $this->service()->createBankDeposit(
            $this->chequePayload('CHK-008', now()->addDays(9)->toDateString(), 5000)
        );

        // A cheque never sat in the drawer, so the drawer is untouched by one
        // waiting to clear — whatever account the form happened to send.
        $this->assertSame(
            $this->service()->getAccountBalance($cash->id),
            $this->service()->getAvailableCashBalance($cash->id)
        );

        // It is held against collections in transit, where the cheque is: two
        // pending cheques must not each pass a guard they together overdraw.
        $transit = app(\App\Services\Accounting\JournalEntryService::class)->inTransitAccount();
        $this->assertSame($transit->id, $deposit->cash_account_id);
        $this->assertSame(
            round($this->service()->getAccountBalance($transit->id) - 5000, 2),
            $this->service()->getAvailableCashBalance($transit->id)
        );
    }

    public function test_available_balance_matches_ledger_when_nothing_is_pending(): void
    {
        $cash = $this->cashAccount();
        $this->service()->createBankDeposit($this->payload([
            'cash_account_id' => $cash->id,
            'amount' => 1000,
            'deposit_type' => 'cash',
        ]));

        $this->assertSame(
            $this->service()->getAccountBalance($cash->id),
            $this->service()->getAvailableCashBalance($cash->id)
        );
    }

    public function test_deleting_a_pending_deposit_does_not_error(): void
    {
        $deposit = $this->service()->createBankDeposit(
            $this->chequePayload('CHK-007', now()->addDays(7)->toDateString())
        );

        $this->service()->deleteBankDeposit($deposit->id);

        $this->assertDatabaseMissing('bank_deposits', ['id' => $deposit->id]);
    }
}
