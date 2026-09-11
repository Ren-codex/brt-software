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

    public function test_cash_deposit_posts_its_journal_entry_immediately(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload());

        $this->assertSame(BankDeposit::STATUS_POSTED, $deposit->status);
        $this->assertNotNull($deposit->posted_at);
        $this->assertCount(1, $this->entriesFor($deposit));
    }

    public function test_future_dated_check_creates_no_journal_entry(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => now()->addDays(10)->toDateString(),
            'check_number' => 'CHK-001',
        ]));

        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->status);
        $this->assertNull($deposit->posted_at);
        $this->assertCount(0, $this->entriesFor($deposit));
    }

    public function test_check_dated_today_posts_immediately(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => now()->toDateString(),
            'check_number' => 'CHK-002',
        ]));

        $this->assertSame(BankDeposit::STATUS_POSTED, $deposit->status);
        $this->assertCount(1, $this->entriesFor($deposit));
    }

    public function test_command_posts_the_check_once_its_date_arrives(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => now()->addDays(3)->toDateString(),
            'check_number' => 'CHK-003',
        ]));

        $this->travelTo(now()->addDays(3));
        $this->artisan('deposits:post-due-checks')->assertSuccessful();

        $deposit->refresh();
        $this->assertSame(BankDeposit::STATUS_POSTED, $deposit->status);
        $this->assertCount(1, $this->entriesFor($deposit));
    }

    public function test_command_does_not_post_a_check_before_its_date(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => now()->addDays(5)->toDateString(),
            'check_number' => 'CHK-004',
        ]));

        $this->artisan('deposits:post-due-checks')->assertSuccessful();

        $deposit->refresh();
        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->status);
        $this->assertCount(0, $this->entriesFor($deposit));
    }

    public function test_command_is_idempotent(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => now()->addDay()->toDateString(),
            'check_number' => 'CHK-005',
        ]));

        $this->travelTo(now()->addDay());
        $this->artisan('deposits:post-due-checks');
        $this->artisan('deposits:post-due-checks');

        $this->assertCount(1, $this->entriesFor($deposit->refresh()));
    }

    public function test_posted_check_entry_carries_the_check_date(): void
    {
        $checkDate = now()->addDays(4)->toDateString();
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => $checkDate,
            'check_number' => 'CHK-006',
        ]));

        $this->travelTo(now()->addDays(4));
        $this->artisan('deposits:post-due-checks');

        $entry = $this->entriesFor($deposit->refresh())->first();
        $this->assertSame($checkDate, \Illuminate\Support\Carbon::parse($entry->entry_date)->toDateString());
    }

    public function test_available_balance_excludes_pending_check_deposits(): void
    {
        $cash = $this->cashAccount();
        $this->service()->createBankDeposit($this->payload([
            'cash_account_id' => $cash->id,
            'amount' => 5000,
            'deposit_type' => 'check',
            'check_date' => now()->addDays(9)->toDateString(),
            'check_number' => 'CHK-008',
        ]));

        // The pending check has not credited cash yet, so the raw ledger balance
        // still shows it. Available must not, or two pending checks could each
        // pass the guard while together overdrawing the account.
        $raw = $this->service()->getAccountBalance($cash->id);
        $available = $this->service()->getAvailableCashBalance($cash->id);

        $this->assertSame(round($raw - 5000, 2), $available);
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
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => now()->addDays(7)->toDateString(),
            'check_number' => 'CHK-007',
        ]));

        $this->service()->deleteBankDeposit($deposit->id);

        $this->assertDatabaseMissing('bank_deposits', ['id' => $deposit->id]);
    }
}
