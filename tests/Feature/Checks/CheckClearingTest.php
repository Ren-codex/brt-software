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

    /**
     * A pending check-type deposit for the given Check, matched the same way
     * CheckRegisterClass::markCleared() matches it back: same check_number
     * and amount.
     */
    private function pendingDepositFor(Check $check): BankDeposit
    {
        return app(CashManagementService::class)->createBankDeposit([
            'cash_account_id' => $this->cashAccount()->id,
            'bank_account_id' => $this->bankAccount()->id,
            'amount' => $check->amount,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'check',
            'check_date' => now()->addDays(3)->toDateString(),
            'check_number' => $check->check_number,
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

        $this->assertSame(BankDeposit::STATUS_POSTED, $deposit->fresh()->status);
        $this->assertNotNull($deposit->fresh()->posted_at);
    }

    /**
     * The other half of the seam above: a bounced check never was money, so
     * its linked deposit must not post either — it stays pending exactly
     * like the check does.
     */
    public function test_bouncing_a_received_check_leaves_its_deposit_pending(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt, ['bank_name' => 'BDO']);
        $deposit = $this->pendingDepositFor($check);

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->fresh()->status);
        $this->assertNull($deposit->fresh()->posted_at);
    }
}
