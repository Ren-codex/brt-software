<?php

namespace Tests\Feature\Checks;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\Check;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\BouncedCheckNotification;
use App\Services\Accounting\CashManagementService;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckClearingTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

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

    /** A pending deposit carrying this cheque, made the way the office does. */
    private function pendingDepositFor(Check $check): BankDeposit
    {
        return app(CashManagementService::class)->createBankDeposit([
            'bank_account_id' => $this->bankAccount()->id,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'check',
            'check_ids' => [$check->id],
        ]);
    }

    /**
     * A deposit of the shape written before deposits carried a cheque id:
     * nothing but a number and an amount to find the cheque by.
     */
    private function legacyDepositFor(Check $check): BankDeposit
    {
        return BankDeposit::create([
            'deposit_no' => 'BD-LEGACY-'.uniqid(),
            'cash_account_id' => $this->cashAccount()->id,
            'bank_account_id' => $this->bankAccount()->id,
            'deposit_type' => BankDeposit::TYPE_CHECK,
            'check_id' => null,
            'amount' => $check->amount,
            'deposit_date' => now()->toDateString(),
            'check_date' => now()->addDays(3)->toDateString(),
            'check_number' => $check->check_number,
            'status' => BankDeposit::STATUS_PENDING,
        ]);
    }

    public function test_bouncing_leaves_the_invoice_owed_and_posts_nothing(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);
        $balanceBefore = (float) $receipt->arInvoice->balance_due;

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        $this->assertSame(Check::STATUS_BOUNCED, $check->fresh()->status);
        $this->assertSame('Insufficient funds', $check->fresh()->bounce_reason);
        $this->assertSame($balanceBefore, (float) $receipt->arInvoice->fresh()->balance_due);
        $this->assertSame(0, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
    }

    public function test_a_cleared_check_cannot_be_bounced(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);
        $check->update(['status' => Check::STATUS_CLEARED]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CheckRegisterClass::class)->markBounced($check, 'Too late');
    }

    /**
     * `notifyReceivingRep()` resolves the User via the Employee side of the
     * relation (User::employee() is hasOne(Employee, 'user_id') — Users don't
     * have an employee_id column). This seeds an Employee linked to a User via
     * user_id, and confirms the notification actually reaches that User.
     */
    public function test_bouncing_notifies_the_receiving_rep(): void
    {
        Notification::fake();

        $receipt = $this->receipt('Check', '000123');
        $repUser = User::factory()->create();
        Employee::where('id', $receipt->arInvoice->sales_order->sales_rep_id)
            ->update(['user_id' => $repUser->id]);

        $check = app(CheckRegisterClass::class)->registerReceived($receipt);

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        Notification::assertSentTo($repUser, BouncedCheckNotification::class);
    }

    /**
     * The seam between the check register and cash management: a check-type
     * bank deposit stays pending until its underlying check is confirmed
     * cleared, and confirming the check is what posts the deposit.
     */
    public function test_confirming_a_received_check_posts_its_pending_deposit(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt, ['bank_name' => 'BDO']);
        $deposit = $this->pendingDepositFor($check);

        app(CheckRegisterClass::class)->markCleared($check);

        $deposit->refresh();
        $this->assertSame(BankDeposit::STATUS_POSTED, $deposit->status);
        $this->assertNotNull($deposit->posted_at);

        // Pin BankDeposit::effectiveDate(): the journal entry this confirm
        // triggers must still carry the check's own date, not the day it was
        // confirmed — the only assertion of this used to live in the
        // now-deleted test_posted_check_entry_carries_the_check_date.
        $entry = JournalEntry::where('source_type', BankDeposit::class)->where('source_id', $deposit->id)->first();
        $this->assertNotNull($entry, 'Confirming the check must post the deposit\'s journal entry.');
        $this->assertSame($deposit->effectiveDate(), \Illuminate\Support\Carbon::parse($entry->entry_date)->toDateString());
    }

    /**
     * A bounced cheque never was money, so its deposit is removed entirely
     * rather than left pending forever — a phantom row that inflates counts
     * and holds balance against the transit account.
     */
    public function test_bouncing_a_received_check_removes_its_deposit(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt, ['bank_name' => 'BDO']);
        $deposit = $this->pendingDepositFor($check);

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        $this->assertNull($deposit->fresh(), 'A bounced cheque\'s deposit must not linger.');
        $this->assertDatabaseMissing('bank_deposits', ['id' => $deposit->id]);
    }

    /**
     * check_number + amount narrows the match a lot but is still not a real
     * link back to the check — two unrelated pending deposits can still
     * collide on both fields. When that happens, posting either one would be
     * a guess about whose money it is, so markCleared() must refuse the
     * whole confirmation rather than pick one, leaving the check pending and
     * both deposits untouched.
     */
    public function test_two_colliding_pending_deposits_refuse_confirmation_and_stay_unposted(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt, ['bank_name' => 'BDO']);
        // Only old rows can collide now: a deposit made by picking the cheque
        // names it outright, and the same cheque cannot be banked twice.
        $depositA = $this->legacyDepositFor($check);
        $depositB = $this->legacyDepositFor($check);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            app(CheckRegisterClass::class)->markCleared($check);
        } finally {
            $this->assertSame(BankDeposit::STATUS_PENDING, $depositA->fresh()->status);
            $this->assertSame(BankDeposit::STATUS_PENDING, $depositB->fresh()->status);
            $this->assertSame(Check::STATUS_PENDING, $check->fresh()->status, 'An ambiguous deposit match must not leave the check cleared either.');
        }
    }
}
