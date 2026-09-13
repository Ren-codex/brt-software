<?php

namespace Tests\Feature\Checks;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Check;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\PurchaseOrder;
use App\Models\Receipt;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * markCleared() is the moment money actually moves, in both directions: an
 * issued check posts DR Accounts Payable / CR Bank dated the clearing (not
 * the post-dated check date, via JournalEntryService::postClearedSupplierCheck),
 * and a received check posts the collection entry via
 * ArInvoiceClass::confirmCheck() and reduces the invoice balance in the same
 * call. Both directions run inside one DB::transaction() in
 * CheckRegisterClass::markCleared() — the last test here proves that wrap
 * actually protects against a half-written clearing, not just that it
 * compiles.
 */
class IssuedCheckClearingTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    /**
     * Copied from IssuedCheckPostingTest (Task 6) rather than shared via a
     * trait, matching how MakesCheckFixtures itself started out as a
     * per-test copy before being extracted — the received-check side has
     * that trait already; the supplier-payment side does not, and this task
     * isn't the one refactoring it.
     *
     * @return array{0: \App\Models\ReceivedStock, 1: \App\Models\ReceivedStockPayment}
     */
    private function supplierPayment(string $mode, float $amount): array
    {
        $user = User::factory()->create();

        $supplier = ListSupplier::create([
            'name' => 'Test Supplier ' . uniqid(), 'address' => 'Zamboanga City',
            'contact_person' => 'Roberto Cruz', 'contact_number' => '09170000000',
            'email' => uniqid() . '@example.com', 'tin' => '000-000-000',
            'is_active' => 1, 'is_blacklisted' => 0,
        ]);

        $status = ListStatus::firstOrCreate(
            ['slug' => 'pending'],
            ['name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333']
        );

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-' . uniqid(),
            'po_date' => now()->toDateString(),
            'total_amount' => $amount,
            'created_by_id' => $user->id,
            'status_id' => $status->id,
        ]);

        $stock = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplier->id,
            'received_no' => 'RS-' . uniqid(), 'received_date' => now(),
            'payment_mode' => 'Credit', 'amount_paid' => 0, 'received_by_id' => $user->id,
        ]);

        $bankAccount = BankAccount::firstOrCreate(
            ['gl_code' => '1020'],
            ['bank_name' => 'BDO', 'account_name' => 'BRT']
        );

        $payment = ReceivedStockPayment::create([
            'received_stock_id' => $stock->id,
            'payment_date' => now()->toDateString(),
            'payment_mode' => $mode,
            'amount_paid' => $amount,
            'bank_account_id' => $bankAccount->id,
            'bank_name' => 'BDO',
            'reference_number' => 'CHK-' . uniqid(),
            'created_by_id' => $user->id,
        ]);

        return [$stock, $payment];
    }

    /**
     * Net balance of the bank GL account a check payment actually posts
     * against. Unlike a bank-transfer payment, resolveCashAccountByPaymentMode()
     * never looks at bank_account_id for a check — every check posts to the
     * generic 1011 "Cash in Bank" account regardless of which BankAccount row
     * the payment references — so this checks 1011, not the fixture's 1020
     * BankAccount GL code.
     */
    private function bankBalance(): float
    {
        $account = Account::where('code', '1011')->first();
        if (!$account) {
            return 0.0;   // the GL account is created lazily on first posting
        }
        $debit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'debit')->sum('amount');
        $credit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }

    public function test_clearing_an_issued_check_posts_the_supplier_payment(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000000);
        $check = app(CheckRegisterClass::class)->registerIssued($payment);

        app(CheckRegisterClass::class)->markCleared($check);

        $this->assertSame(Check::STATUS_CLEARED, $check->fresh()->status);
        $this->assertSame(-1000000.0, $this->bankBalance(), 'The money leaves when the check is cashed.');
    }

    public function test_clearing_a_received_check_posts_the_collection_and_reduces_the_balance(): void
    {
        $receipt = $this->receipt('Check', '000123');
        // registerReceived() carries bank_name over from the receipt as-is;
        // confirmCheck() (called from markCleared() for the received
        // direction) requires a bank name, so give the register row one —
        // MakesCheckFixtures::receipt() doesn't set one by default.
        $check = app(CheckRegisterClass::class)->registerReceived($receipt, ['bank_name' => 'BDO']);

        app(CheckRegisterClass::class)->markCleared($check);

        $this->assertSame(Check::STATUS_CLEARED, $check->fresh()->status);
        $this->assertSame(1, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        $this->assertSame(0.0, (float) $receipt->arInvoice->fresh()->balance_due);
    }

    public function test_a_non_pending_check_cannot_be_cleared(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000000);
        $check = app(CheckRegisterClass::class)->registerIssued($payment);
        $check->update(['status' => Check::STATUS_BOUNCED]);

        $this->expectException(ValidationException::class);

        app(CheckRegisterClass::class)->markCleared($check);
    }

    /**
     * Task 3 ruled that ArInvoiceClass::confirmCheck() has no transaction of
     * its own — it was safe only because its single caller wrapped it in
     * HandlesTransaction. markCleared() is its second caller, and the brief
     * for this task wraps markCleared() in DB::transaction() specifically so
     * that dependency isn't silently relied on twice. This test forces a
     * failure immediately after the real posting call succeeds (simulating
     * "the posting worked but something later in the transaction blew up")
     * and asserts that the whole clearing — the journal entry included —
     * rolled back together, rather than leaving a posted entry next to a
     * check that still reads "pending".
     */
    public function test_a_failure_after_posting_rolls_back_the_whole_clearing(): void
    {
        $throwingService = new class extends JournalEntryService
        {
            public function postClearedSupplierCheck(ReceivedStock $receivedStock, ReceivedStockPayment $payment, $clearedDate): ?JournalEntry
            {
                // Do the real posting first, so the transaction actually has
                // something to roll back — then blow up, simulating a
                // failure that lands after the ledger write succeeds.
                parent::postClearedSupplierCheck($receivedStock, $payment, $clearedDate);

                throw new \RuntimeException('Simulated failure after the ledger entry posted.');
            }
        };
        $this->app->instance(JournalEntryService::class, $throwingService);

        [$stock, $payment] = $this->supplierPayment('Check', 1000000);
        $check = app(CheckRegisterClass::class)->registerIssued($payment);

        try {
            app(CheckRegisterClass::class)->markCleared($check);
            $this->fail('Expected the simulated failure to propagate out of markCleared().');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure after the ledger entry posted.', $e->getMessage());
        }

        $this->assertSame(Check::STATUS_PENDING, $check->fresh()->status, 'The check must not read cleared when the posting was rolled back.');
        $this->assertSame(
            0,
            JournalEntry::where('source_type', ReceivedStockPayment::class)->where('source_id', $payment->id)->count(),
            'The journal entry the throwing service posted must not survive the rollback.'
        );
        $this->assertSame(0.0, $this->bankBalance(), 'No money may appear to have left the bank once the transaction rolled back.');
    }
}
