<?php

namespace Tests\Feature\Checks;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\JournalEntryLine;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\PurchaseOrder;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner post-dates supplier checks to the day she expects funds to
 * arrive. Posting the payment entry immediately would credit the bank weeks
 * before the money actually leaves, routinely driving the account negative.
 * recordReceivedStockPaymentEntry() must hold a check payment until it is
 * cleared (Task 7 posts it then), while every other payment mode still
 * posts immediately.
 */
class IssuedCheckPostingTest extends TestCase
{
    use RefreshDatabase;

    private ?BankAccount $bankAccount = null;

    /** @return array{0: \App\Models\ReceivedStock, 1: \App\Models\ReceivedStockPayment} */
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

        $this->bankAccount = BankAccount::firstOrCreate(
            ['gl_code' => '1020'],
            ['bank_name' => 'BDO', 'account_name' => 'BRT']
        );

        $payment = ReceivedStockPayment::create([
            'received_stock_id' => $stock->id,
            'payment_date' => now()->toDateString(),
            'payment_mode' => $mode,
            'amount_paid' => $amount,
            'bank_account_id' => $this->bankAccount->id,
            'bank_name' => 'BDO',
            'reference_number' => 'CHK-' . uniqid(),
            'created_by_id' => $user->id,
        ]);

        return [$stock, $payment];
    }

    /** Net balance of the bank GL account the fixture draws on. */
    private function bankBalance(): float
    {
        $account = Account::where('code', '1020')->first();
        if (!$account) {
            return 0.0;   // the GL account is created lazily on first posting
        }
        $debit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'debit')->sum('amount');
        $credit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }

    public function test_a_supplier_check_posts_nothing_and_leaves_the_bank_alone(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000000);

        $entry = app(JournalEntryService::class)->recordReceivedStockPaymentEntry($stock, $payment);

        $this->assertNull($entry);
        $this->assertSame(0.0, $this->bankBalance(), 'A post-dated check must not drive the bank negative.');
    }

    public function test_a_bank_transfer_still_posts_immediately(): void
    {
        [$stock, $payment] = $this->supplierPayment('Bank Transfer', 5000);

        $this->assertNotNull(app(JournalEntryService::class)->recordReceivedStockPaymentEntry($stock, $payment));
    }

    public function test_cash_still_posts_immediately(): void
    {
        [$stock, $payment] = $this->supplierPayment('Cash on Hand', 2500);

        $this->assertNotNull(app(JournalEntryService::class)->recordReceivedStockPaymentEntry($stock, $payment));
    }

    /**
     * Task 2's review found the sibling guard in recordReceiptEntry() didn't
     * trim, so a padded value like 'Check ' would slip through and post
     * money for a pending check. Both guards were fixed to trim identically —
     * this asserts the fix on the supplier-payment side.
     */
    public function test_a_padded_check_payment_mode_is_still_guarded(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check ', 1000000);

        $entry = app(JournalEntryService::class)->recordReceivedStockPaymentEntry($stock, $payment);

        $this->assertNull($entry, 'A padded "Check " payment mode must not slip past the guard.');
        $this->assertSame(0.0, $this->bankBalance());
    }
}
