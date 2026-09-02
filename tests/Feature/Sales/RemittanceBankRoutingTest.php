<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\Remittance;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifying a remittance moves each mode's money out of Undeposited Collections
 * into the account it actually reached. A bank transfer belongs in the bank the
 * receipt names — BDO, BPI — not a catch-all Cash in Bank, or the bank
 * reconciliation has nothing to match against.
 */
class RemittanceBankRoutingTest extends TestCase
{
    use RefreshDatabase;

    private BankAccount $bdo;
    private BankAccount $bpi;

    protected function setUp(): void
    {
        parent::setUp();

        ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $this->actingAs(User::factory()->create());

        $this->bdo = BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT Operating', 'account_number' => '001',
            'gl_code' => '1020', 'balance' => 0, 'is_active' => 1,
        ]);

        $this->bpi = BankAccount::create([
            'bank_name' => 'BPI', 'account_name' => 'BRT Payroll', 'account_number' => '002',
            'gl_code' => '1021', 'balance' => 0, 'is_active' => 1,
        ]);
    }

    private function remittanceWith(array $receipts, array $receivedBreakdown): Remittance
    {
        $total = collect($receipts)->sum('amount_paid');

        $remittance = Remittance::create([
            'remittance_no' => 'RM-TEST-' . uniqid(),
            'remittance_date' => now()->toDateString(),
            'summary' => [],
            'total_amount' => $total,
            'received_amount' => array_sum($receivedBreakdown),
            'received_breakdown' => $receivedBreakdown,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'created_by_id' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Receipts hang off an invoice; the routing only cares about the
        // payment mode and bank account, so a bare invoice is enough.
        $order = SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'total_amount' => $total, 'total_discount' => 0,
            'added_by_id' => auth()->id(), 'requires_batch_approval' => false,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
        ]);

        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => $total,
            'amount_paid' => $total, 'balance_due' => 0, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
        ]);

        foreach ($receipts as $i => $attributes) {
            Receipt::create(array_merge([
                'receipt_number' => 'OR-TEST-' . uniqid() . $i,
                'receipt_type' => 'payment',
                'receipt_date' => now()->toDateString(),
                'balance_due' => 0,
                'ar_invoice_id' => $invoice->id,
                'remittance_id' => $remittance->id,
                'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            ], $attributes));
        }

        return $remittance->fresh('receipts');
    }

    /** @return array<string, float> debit total keyed by account code */
    private function debitsByAccount(JournalEntry $entry): array
    {
        return $entry->lines->where('line_type', 'debit')
            ->groupBy(fn ($line) => $line->account->code)
            ->map(fn ($group) => round((float) $group->sum('amount'), 2))
            ->all();
    }

    public function test_a_bank_transfer_lands_in_the_bank_account_the_receipt_names(): void
    {
        $remittance = $this->remittanceWith(
            [['payment_mode' => 'Bank Transfer', 'amount_paid' => 5000, 'bank_account_id' => $this->bdo->id]],
            ['Bank Transfer' => 5000]
        );

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance);

        $debits = $this->debitsByAccount($entry->fresh('lines.account'));

        $this->assertArrayHasKey('1020', $debits, 'Expected the debit to hit BDO (1020).');
        $this->assertEquals(5000.0, $debits['1020']);
        $this->assertArrayNotHasKey('1011', $debits, 'Should no longer fall back to generic Cash in Bank.');
    }

    public function test_transfers_to_different_banks_are_posted_separately(): void
    {
        $remittance = $this->remittanceWith([
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 3000, 'bank_account_id' => $this->bdo->id],
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 1000, 'bank_account_id' => $this->bpi->id],
        ], ['Bank Transfer' => 4000]);

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance);
        $debits = $this->debitsByAccount($entry->fresh('lines.account'));

        $this->assertEquals(3000.0, $debits['1020']);
        $this->assertEquals(1000.0, $debits['1021']);
    }

    public function test_a_shortfall_is_shared_across_banks_in_proportion_to_what_each_expected(): void
    {
        // 3,000 expected from BDO and 1,000 from BPI, but only 2,000 turned up.
        $remittance = $this->remittanceWith([
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 3000, 'bank_account_id' => $this->bdo->id],
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 1000, 'bank_account_id' => $this->bpi->id],
        ], ['Bank Transfer' => 2000]);

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance);
        $debits = $this->debitsByAccount($entry->fresh('lines.account'));

        $this->assertEquals(1500.0, $debits['1020']);
        $this->assertEquals(500.0, $debits['1021']);
        $this->assertEquals(2000.0, round(array_sum([$debits['1020'], $debits['1021']]), 2));
    }

    public function test_the_portions_always_add_back_to_the_amount_received(): void
    {
        // A ratio that does not divide cleanly, so rounding has somewhere to go.
        $remittance = $this->remittanceWith([
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 1, 'bank_account_id' => $this->bdo->id],
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 1, 'bank_account_id' => $this->bpi->id],
        ], ['Bank Transfer' => 100.01]);

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance);
        $debits = $this->debitsByAccount($entry->fresh('lines.account'));

        $this->assertEquals(100.01, round($debits['1020'] + $debits['1021'], 2));
    }

    public function test_cash_still_posts_to_cash_and_is_unaffected_by_bank_routing(): void
    {
        $remittance = $this->remittanceWith(
            [['payment_mode' => 'Cash', 'amount_paid' => 800]],
            ['Cash' => 800]
        );

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance);
        $debits = $this->debitsByAccount($entry->fresh('lines.account'));

        $this->assertEquals(800.0, $debits['1000']);
    }

    public function test_a_transfer_with_no_bank_account_still_falls_back_to_cash_in_bank(): void
    {
        $remittance = $this->remittanceWith(
            [['payment_mode' => 'Bank Transfer', 'amount_paid' => 700]],
            ['Bank Transfer' => 700]
        );

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance);
        $debits = $this->debitsByAccount($entry->fresh('lines.account'));

        $this->assertEquals(700.0, $debits['1011']);
    }

    public function test_a_mode_the_verifier_added_by_hand_still_posts(): void
    {
        // No receipt backs 'GCash' — the verifier typed it in during approval.
        $remittance = $this->remittanceWith(
            [['payment_mode' => 'Cash', 'amount_paid' => 500]],
            ['Cash' => 500, 'GCash' => 250]
        );

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance);
        $debits = $this->debitsByAccount($entry->fresh('lines.account'));

        $this->assertEquals(500.0, $debits['1000']);
        $this->assertEquals(250.0, $debits['1012']);
    }

    public function test_the_entry_still_balances_when_several_banks_are_involved(): void
    {
        $remittance = $this->remittanceWith([
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 3000, 'bank_account_id' => $this->bdo->id],
            ['payment_mode' => 'Bank Transfer', 'amount_paid' => 1000, 'bank_account_id' => $this->bpi->id],
            ['payment_mode' => 'Cash', 'amount_paid' => 2000],
        ], ['Bank Transfer' => 4000, 'Cash' => 2000]);

        $entry = app(JournalEntryService::class)->recordRemittanceApprovalEntry($remittance)->fresh('lines');

        $debits = round((float) $entry->lines->where('line_type', 'debit')->sum('amount'), 2);
        $credits = round((float) $entry->lines->where('line_type', 'credit')->sum('amount'), 2);

        $this->assertEquals($debits, $credits, 'Journal entry must balance.');
    }
}
