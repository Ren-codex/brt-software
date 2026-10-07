<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\InventoryStocks;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * COD means the sacks do not leave the truck unless the money does.
 *
 * The business rule: whatever the customer keeps, they pay for on handover.
 * Whatever they refuse goes back to the warehouse and comes off the invoice.
 * There is no state where a COD order has been delivered and is still owed —
 * if one appears, either someone bypassed this or an instrument has not
 * cleared yet.
 *
 * Credit is untouched: goods go out and the money follows on terms.
 */
class CodPaidOnHandoverTest extends TestCase
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

    private function deliver(array $payload = [])
    {
        $order = SalesOrder::firstOrFail();

        return $this->actingAs($this->user)->put('/sales-orders/'.$order->id, array_merge([
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
        ], $payload));
    }

    public function test_a_cod_order_cannot_be_delivered_with_nothing_collected(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->deliver()->assertSessionHasErrors('collected_amount');

        $this->assertNull(SalesOrder::firstOrFail()->delivered_at);
    }

    public function test_a_cod_order_cannot_be_delivered_part_paid(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        // Taking everything but paying for some of it is the case the rule
        // exists to stop: reduce what they take instead.
        $this->deliver([
            'collected_amount' => 1000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasErrors('collected_amount');

        $this->assertNull(SalesOrder::firstOrFail()->delivered_at);
    }

    public function test_paying_in_full_delivers_it(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->deliver([
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(SalesOrder::firstOrFail()->delivered_at);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_paying_by_check_delivers_it_even_though_the_balance_stays_open(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        // The customer handed over a cheque, so they have paid. The balance
        // waits on the bank, which is why the guard reads what was collected
        // rather than the invoice.
        $this->deliver([
            'collected_amount' => 3000,
            'collected_mode' => 'Check',
            'collected_reference' => 'CHK-7781',
            'collected_check_date' => now()->addDays(14)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(SalesOrder::firstOrFail()->delivered_at);
        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);
        $this->assertNull(Receipt::firstOrFail()->confirmed_at);
    }

    public function test_taking_less_and_paying_for_that_much_is_allowed(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        $item = $order->items->first();
        $kept = (int) floor($item->quantity / 2);
        $keptValue = round($kept * (float) $item->price, 2);

        $before = (float) InventoryStocks::where('product_id', $item->product_id)
            ->where('batch_code', $item->batch_code)->sum('quantity');

        $this->deliver([
            'accepted_quantities' => [$item->id => $kept],
            'refusal_reasons' => [$item->id => 'Customer could only take half'],
            'collected_amount' => $keptValue,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(SalesOrder::firstOrFail()->delivered_at);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);

        // What they did not take went back to its own batch.
        $after = (float) InventoryStocks::where('product_id', $item->product_id)
            ->where('batch_code', $item->batch_code)->sum('quantity');
        $this->assertSame($before + ($item->quantity - $kept), $after);
    }

    public function test_taking_less_but_paying_the_old_total_is_still_refused(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        $item = $order->items->first();

        // Paying for only part of the reduced amount.
        $this->deliver([
            'accepted_quantities' => [$item->id => (int) floor($item->quantity / 2)],
            'refusal_reasons' => [$item->id => 'Took half'],
            'collected_amount' => 1,
            'collected_mode' => 'Cash',
        ])->assertSessionHasErrors('collected_amount');
    }

    public function test_a_credit_order_still_delivers_unpaid(): void
    {
        $this->postCod([
            'payment_mode' => 'Credit',
            'due_date' => now()->addDays(30)->toDateString(),
            'delivery_date' => null,
        ])->assertSessionHasNoErrors();

        $this->deliver()->assertSessionHasNoErrors();

        $this->assertNotNull(SalesOrder::firstOrFail()->delivered_at);
        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);
    }
}
