<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Picking a batch other than the oldest used to hold the whole order for an
 * approver, so a cash sale would not close or issue its receipt until someone
 * signed. The deviation from FIFO is still recorded on the line; it just no
 * longer stops the sale.
 */
class BatchOverrideNeedsNoApprovalTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
    }

    private function cashOrderWithOverride(): void
    {
        $payload = $this->payload([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            'payment_lines' => [[
                'payment_mode' => 'Cash',
                'payment_amount' => 3000,
            ]],
        ]);
        $payload['items'][0]['is_batch_override'] = true;

        $this->actingAs($this->user)->post('/sales-orders', $payload)->assertSessionHasNoErrors();
    }

    public function test_a_cash_sale_on_a_newer_batch_closes_on_save(): void
    {
        $this->cashOrderWithOverride();

        $order = SalesOrder::firstOrFail();
        $this->assertSame('closed', $order->status->slug);
        $this->assertFalse((bool) $order->requires_batch_approval);
    }

    public function test_it_issues_the_receipt_without_waiting_for_a_signature(): void
    {
        $this->cashOrderWithOverride();

        $invoice = ArInvoice::firstOrFail();
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertTrue($invoice->receipts()->exists());
    }

    public function test_the_line_still_records_that_it_skipped_fifo(): void
    {
        // Worth seeing later even though nobody has to approve it.
        $this->cashOrderWithOverride();

        $this->assertTrue((bool) SalesOrder::with('items')->firstOrFail()->items->first()->is_batch_override);
    }
}
