<?php

namespace Tests\Feature\Libraries;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Libraries\ReceiptClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Guards against ReceiptClass overwriting partially-returned / sales-returned
 * SO status when a receipt is added, updated, or deleted.
 */
class ReceiptStatusPreservationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        foreach ([
            'partially-returned', 'sales-returned', 'partially-paid',
            'paid', 'unpaid', 'pending', 'closed', 'for-payment',
        ] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name'       => ucwords(str_replace('-', ' ', $slug)),
                'text_color' => '#fff',
                'bg_color'   => '#333',
            ]);
        }

        $this->customer = Customer::create([
            'name'           => 'Test Customer',
            'address'        => 'Addr',
            'contact_number' => '09000000001',
            'added_by_id'    => $this->user->id,
            'is_active'      => true,
            'is_regular'     => false,
            'is_blacklisted' => false,
        ]);
    }

    private function makeSoWithInvoice(string $statusSlug, float $amountDue, float $amountPaid, float $balanceDue): array
    {
        $so = SalesOrder::create([
            'so_number'    => 'SO-RCPT-' . uniqid(),
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', $statusSlug)->first()->id,
            'total_amount' => $amountDue,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        $invoice = ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => ListStatus::where('slug', $amountPaid >= $amountDue ? 'paid' : 'partially-paid')->first()->id,
            'invoice_number' => 'INV-' . $so->so_number,
            'invoice_date'   => now()->toDateString(),
            'amount_due'     => $amountDue,
            'amount_paid'    => $amountPaid,
            'balance_due'    => $balanceDue,
        ]);

        return [$so, $invoice];
    }

    private function makeReceipt(ArInvoice $invoice, float $amountPaid, string $statusSlug = 'paid'): Receipt
    {
        return Receipt::create([
            'ar_invoice_id'  => $invoice->id,
            'customer_id'    => $this->customer->id,
            'status_id'      => ListStatus::where('slug', $statusSlug)->first()->id,
            'receipt_number' => 'REC-' . uniqid(),
            'receipt_type'   => 'payment',
            'receipt_date'   => now()->toDateString(),
            'amount_paid'    => $amountPaid,
            'payment_mode'   => 'cash',
        ]);
    }

    // ─── save() ──────────────────────────────────────────────────────────────

    public function test_adding_receipt_preserves_partially_returned_status(): void
    {
        // SO is partially-returned, ₱1,000 balance remaining (replacement extra charge)
        [$so, $invoice] = $this->makeSoWithInvoice('partially-returned', 3000, 2000, 1000);

        $request = new Request();
        $request->merge([
            'ar_invoice_id' => $invoice->id,
            'receipt_date'  => now()->toDateString(),
            'amount_paid'   => 500.00,
            'payment_mode'  => 'cash',
        ]);

        app(ReceiptClass::class)->save($request);

        $this->assertEquals('partially-returned', $so->fresh()->status->slug,
            'Adding a receipt must not overwrite partially-returned SO status');
    }

    public function test_adding_receipt_preserves_sales_returned_status(): void
    {
        [$so, $invoice] = $this->makeSoWithInvoice('sales-returned', 2000, 1000, 1000);

        $request = new Request();
        $request->merge([
            'ar_invoice_id' => $invoice->id,
            'receipt_date'  => now()->toDateString(),
            'amount_paid'   => 500.00,
            'payment_mode'  => 'cash',
        ]);

        app(ReceiptClass::class)->save($request);

        $this->assertEquals('sales-returned', $so->fresh()->status->slug,
            'Adding a receipt must not overwrite sales-returned SO status');
    }

    public function test_adding_receipt_that_fully_pays_returned_so_marks_it_closed(): void
    {
        // If the customer pays off the full remaining balance on a returned SO, it should close
        [$so, $invoice] = $this->makeSoWithInvoice('partially-returned', 2000, 1000, 1000);

        $request = new Request();
        $request->merge([
            'ar_invoice_id' => $invoice->id,
            'receipt_date'  => now()->toDateString(),
            'amount_paid'   => 1000.00, // clears the balance
            'payment_mode'  => 'cash',
        ]);

        app(ReceiptClass::class)->save($request);

        $this->assertEquals('closed', $so->fresh()->status->slug,
            'Fully paying a returned SO must mark it closed');
    }

    public function test_paying_off_sales_returned_so_keeps_it_sales_returned(): void
    {
        // sales-returned SO with an extra replacement charge that is now being paid off
        [$so, $invoice] = $this->makeSoWithInvoice('sales-returned', 500, 0, 500);

        $request = new Request();
        $request->merge([
            'ar_invoice_id' => $invoice->id,
            'receipt_date'  => now()->toDateString(),
            'amount_paid'   => 500.00, // clears the full balance
            'payment_mode'  => 'cash',
        ]);

        app(ReceiptClass::class)->save($request);

        $this->assertEquals('sales-returned', $so->fresh()->status->slug,
            'Paying off a sales-returned SO must NOT mark it closed — it stays sales-returned');
    }

    // ─── update() ────────────────────────────────────────────────────────────

    public function test_updating_receipt_preserves_partially_returned_status(): void
    {
        [$so, $invoice] = $this->makeSoWithInvoice('partially-returned', 3000, 1000, 2000);
        $receipt = $this->makeReceipt($invoice, 1000, 'paid');

        $request = new Request();
        $request->merge([
            'id'            => $receipt->id,
            'ar_invoice_id' => $invoice->id,
            'receipt_date'  => now()->toDateString(),
            'amount_paid'   => 800.00, // reduce the payment slightly
            'payment_mode'  => 'cash',
        ]);

        app(ReceiptClass::class)->update($request);

        $this->assertEquals('partially-returned', $so->fresh()->status->slug,
            'Updating a receipt must not overwrite partially-returned SO status');
    }

    // ─── delete() ────────────────────────────────────────────────────────────

    public function test_deleting_receipt_preserves_partially_returned_status(): void
    {
        [$so, $invoice] = $this->makeSoWithInvoice('partially-returned', 3000, 1000, 2000);
        $receipt = $this->makeReceipt($invoice, 1000, 'paid');

        app(ReceiptClass::class)->delete($receipt->id);

        $this->assertEquals('partially-returned', $so->fresh()->status->slug,
            'Deleting a receipt must not overwrite partially-returned SO status');
    }

    public function test_deleting_receipt_preserves_sales_returned_status(): void
    {
        [$so, $invoice] = $this->makeSoWithInvoice('sales-returned', 2000, 1000, 1000);
        $receipt = $this->makeReceipt($invoice, 1000, 'paid');

        app(ReceiptClass::class)->delete($receipt->id);

        $this->assertEquals('sales-returned', $so->fresh()->status->slug,
            'Deleting a receipt must not overwrite sales-returned SO status');
    }

    // ─── Regression: non-returned SO still updates normally ──────────────────

    public function test_adding_receipt_still_marks_non_returned_so_as_partially_paid(): void
    {
        [$so, $invoice] = $this->makeSoWithInvoice('for-payment', 3000, 0, 3000);

        $request = new Request();
        $request->merge([
            'ar_invoice_id' => $invoice->id,
            'receipt_date'  => now()->toDateString(),
            'amount_paid'   => 1000.00,
            'payment_mode'  => 'cash',
        ]);

        app(ReceiptClass::class)->save($request);

        $this->assertEquals('partially-paid', $so->fresh()->status->slug,
            'Normal (non-returned) SO must still become partially-paid after partial payment');
    }
}
