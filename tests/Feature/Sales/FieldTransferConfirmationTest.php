<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A cheque does not reduce an invoice until someone confirms it with the bank.
 * A bank transfer counted the moment a reference number was typed — so a driver
 * phoning one in from the road marked the invoice paid on their word alone.
 *
 * Money collected in the field now waits for the same confirmation. A transfer
 * taken at the counter is unchanged: the cashier is looking at it.
 */
class FieldTransferConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
    }

    private function payByTransfer(): ArInvoice
    {
        $invoice = ArInvoice::firstOrFail();

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Bank Transfer',
            'reference_number' => 'GC-99881',
        ])->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    public function test_a_field_transfer_leaves_the_invoice_unpaid_until_confirmed(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $invoice = $this->payByTransfer();

        $this->assertSame(3000.0, (float) $invoice->balance_due);
        $this->assertNull(Receipt::firstOrFail()->confirmed_at);
    }

    public function test_confirming_a_field_transfer_releases_it(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->payByTransfer();
        $receipt = Receipt::firstOrFail();

        $this->actingAs($this->user)
            ->put('/receipts/'.$receipt->id.'/confirm-check', ['bank_name' => 'BPI'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
        $this->assertNotNull($receipt->fresh()->confirmed_at);
    }

    public function test_a_credit_customers_transfer_still_settles_immediately(): void
    {
        // Sent bank to bank, so the office sees it without a driver's word.
        $this->postCod([
            'payment_mode' => 'Credit',
            'due_date' => now()->toDateString(),
            'delivery_date' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $this->payByTransfer()->balance_due);
    }

    public function test_a_counter_transfer_still_settles_immediately(): void
    {
        // A cash sale settled by transfer at the counter is not a field
        // collection: the cashier is looking at the confirmation.
        $this->postCod([
            'payment_mode' => 'Credit',
            'due_date' => now()->toDateString(),
            'delivery_date' => null,
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $order->update(['payment_mode' => 'Cash']);

        $invoice = $this->payByTransfer();

        $this->assertSame(0.0, (float) $invoice->balance_due);
    }
}
