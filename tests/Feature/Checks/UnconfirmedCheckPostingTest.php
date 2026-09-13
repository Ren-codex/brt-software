<?php

namespace Tests\Feature\Checks;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnconfirmedCheckPostingTest extends TestCase
{
    use RefreshDatabase;

    private function receipt(string $mode): Receipt
    {
        foreach (['unpaid' => 'Unpaid', 'pending' => 'Pending', 'for-payment' => 'For Payment'] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Juan', 'address' => 'Z', 'contact_number' => '09170000000',
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);
        $order = SalesOrder::create([
            'so_number' => 'SO-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 5000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id, 'requires_batch_approval' => false,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 5000,
            'amount_paid' => 0, 'balance_due' => 5000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
        ]);

        return Receipt::create([
            'ar_invoice_id' => $invoice->id, 'customer_id' => $customer->id,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'receipt_number' => 'OR-' . uniqid(), 'receipt_type' => 'payment',
            'receipt_date' => now()->toDateString(), 'amount_paid' => 5000,
            'balance_due' => 5000, 'payment_mode' => $mode,
        ]);
    }

    public function test_a_check_receipt_posts_nothing_until_confirmed(): void
    {
        $receipt = $this->receipt('Check');

        $entry = app(JournalEntryService::class)->recordReceiptEntry($receipt);

        $this->assertNull($entry);
        $this->assertSame(0, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
    }

    public function test_a_cash_receipt_still_posts_immediately(): void
    {
        $receipt = $this->receipt('Cash');

        $entry = app(JournalEntryService::class)->recordReceiptEntry($receipt);

        $this->assertNotNull($entry);
    }
}
