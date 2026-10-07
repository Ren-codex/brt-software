<?php

namespace Tests\Feature\Sales;

use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Recording a handover must never drag an order backwards.
 *
 * Orders closed before the delivery rules existed carry no delivery stamp, so
 * re-deriving their status reads as For Release. A handover on one of their
 * receipts would knock a finished order back into the worklist for a reason
 * that has nothing to do with it.
 */
class HandoverDoesNotReopenOldOrdersTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
    }

    public function test_a_legacy_closed_order_stays_closed(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        // The shape of a record from before deliveries were tracked: settled
        // and closed, with nothing saying it ever arrived.
        $invoice = $order->arInvoices()->first();
        $invoice->update(['balance_due' => 0, 'amount_paid' => $invoice->amount_due]);
        $order->update([
            'delivered_at' => null,
            'status_id' => ListStatus::where('slug', 'closed')->firstOrFail()->id,
        ]);

        $receipt = Receipt::create([
            'ar_invoice_id' => $invoice->id,
            'customer_id' => $order->customer_id,
            'receipt_number' => 'OR-LEGACY-1',
            'receipt_type' => 'payment',
            'receipt_date' => now()->subYear()->toDateString(),
            'amount_paid' => $invoice->amount_due,
            'balance_due' => 0,
            'payment_mode' => 'Cash',
            'status_id' => ListStatus::where('slug', 'pending')->firstOrFail()->id,
            'held_by_employee_id' => $order->driver_id,
        ]);

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', [
                'held_by_employee_id' => \App\Models\Employee::firstOrCreate(
                    ['mobile' => '09170000222'],
                    ['firstname' => 'Office', 'lastname' => 'Rep', 'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None']
                )->id,
            ])
            ->assertOk();

        $this->assertSame('closed', $order->fresh('status')->status->slug);
    }
}
