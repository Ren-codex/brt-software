<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A bank transfer collected at the door now settles like cash: it reduces the
 * invoice the moment it is recorded and is accounted for through remittance,
 * not held back for a separate check-register confirmation. The office still
 * verifies it landed — that is the remittance, which moves the money out of
 * Undeposited Collections into the bank and takes the order out of For Turnover.
 *
 * Only a cheque is held back now, because a cheque can bounce.
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
            'bank_account_id' => $this->fixtureBank()->id,
        ])->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    public function test_a_cod_field_transfer_settles_the_invoice_immediately(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        // Settled on the spot, like cash — no confirmation step stands between
        // the collection and the balance.
        $this->assertSame(0.0, (float) $this->payByTransfer()->balance_due);
    }

    public function test_a_cod_field_transfer_is_left_for_remittance_not_a_confirmation(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->payByTransfer();

        $receipt = Receipt::firstOrFail();

        // Nothing confirms it in the check register; it waits on remittance, so
        // it carries no confirmation stamp and no remittance yet.
        $this->assertNull($receipt->confirmed_at);
        $this->assertNull($receipt->remittance_id);

        // The order is paid and delivered but the money is not verified in,
        // so it rests in For Turnover rather than claiming to be Closed.
        $this->assertSame('for-turnover', SalesOrder::firstOrFail()->fresh('status')->status->slug);
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
