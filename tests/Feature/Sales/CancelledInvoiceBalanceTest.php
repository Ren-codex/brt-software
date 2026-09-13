<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cancelling an order reversed its journal entries and marked the invoice
 * cancelled, but left balance_due at the full amount. The ledger said nothing
 * was owed while the invoice still said it was, and the dashboard totals
 * balance_due, so cancelled orders inflated outstanding receivables — ₱52,040
 * of ₱62,800 on production as of 2026-09-14.
 */
class CancelledInvoiceBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function statuses(): void
    {
        foreach ([
            'unpaid' => 'Unpaid', 'partially-paid' => 'Partially Paid', 'paid' => 'Paid',
            'for-payment' => 'For Payment', 'closed' => 'Closed', 'pending' => 'Pending',
            'cancelled' => 'Cancelled',
        ] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }
    }

    private function makeOrder(float $amount): SalesOrder
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Juan Dela Cruz', 'address' => 'Zamboanga', 'contact_number' => '09170000000',
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        $order = SalesOrder::create([
            'so_number' => 'SO-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => $amount, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id,
            'requires_batch_approval' => false,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => $amount,
            'amount_paid' => 0, 'balance_due' => $amount, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
        ]);

        return $order->fresh();
    }

    public function test_cancelling_an_order_clears_the_invoice_balance(): void
    {
        $this->statuses();
        $order = $this->makeOrder(51000);

        app(SalesOrderClass::class)->cancel($order->id, 'Customer backed out');

        $invoice = ArInvoice::where('sales_order_id', $order->id)->first();

        $this->assertSame('cancelled', $invoice->status->slug);
        $this->assertSame(0.0, (float) $invoice->balance_due, 'A cancelled invoice cannot still be owed.');
    }

    public function test_cancelled_invoices_do_not_count_as_outstanding_receivables(): void
    {
        $this->statuses();
        $live = $this->makeOrder(10760);
        $cancelled = $this->makeOrder(51000);

        app(SalesOrderClass::class)->cancel($cancelled->id, 'Customer backed out');

        $this->assertSame(10760.0, (float) ArInvoice::outstanding()->sum('balance_due'));
    }

    public function test_amount_due_is_kept_as_history(): void
    {
        $this->statuses();
        $order = $this->makeOrder(51000);

        app(SalesOrderClass::class)->cancel($order->id, 'Customer backed out');

        $invoice = ArInvoice::where('sales_order_id', $order->id)->first();

        $this->assertSame(51000.0, (float) $invoice->amount_due, 'What was invoiced stays on the record.');
    }
}
