<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\InventoryStocks;
use App\Models\SalesOrder;
use App\Models\SalesOrderDeliveryRefusal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A customer can take half a delivery and send the rest back. That happens
 * before any money changes hands, so nothing is refunded — the refused goods
 * return to their batch and the invoice shrinks to what was kept, leaving the
 * driver collecting the right amount.
 */
class DeliveryAcceptanceTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
    }

    private function deliver(SalesOrder $order, array $accepted, array $reasons = [])
    {
        return $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'accepted_quantities' => $accepted,
            'refusal_reasons' => $reasons,
        ]);
    }

    private function batchQuantity(): int
    {
        return (int) InventoryStocks::where('batch_code', 'BATCH-001')->sum('quantity');
    }

    public function test_a_short_quantity_returns_stock_and_shrinks_the_invoice(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        $item = $order->items->first();
        $stockBefore = $this->batchQuantity();

        $this->deliver($order, [$item->id => 1], [$item->id => 'Customer only needed one'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1500.0, (float) $order->fresh()->total_amount);
        $this->assertSame(1500.0, (float) ArInvoice::firstOrFail()->balance_due);
        $this->assertSame($stockBefore + 1, $this->batchQuantity());

        $refusal = SalesOrderDeliveryRefusal::firstOrFail();
        $this->assertSame(1, $refusal->refused_quantity);
        $this->assertSame('Customer only needed one', $refusal->reason);
        $this->assertSame('BATCH-001', $refusal->batch_code);
    }

    public function test_accepting_everything_changes_nothing(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        $stockBefore = $this->batchQuantity();

        $this->deliver($order, [$order->items->first()->id => 2])->assertSessionHasNoErrors();

        $this->assertSame(3000.0, (float) $order->fresh()->total_amount);
        $this->assertSame($stockBefore, $this->batchQuantity());
        $this->assertSame(0, SalesOrderDeliveryRefusal::count());
    }

    public function test_refusing_everything_empties_the_invoice_but_still_delivers(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();

        $this->deliver($order, [$order->items->first()->id => 0])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(0.0, (float) $order->total_amount);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotSame('cancelled', optional($order->status)->slug);
    }

    public function test_accepting_more_than_was_ordered_is_rejected(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();

        $this->deliver($order, [$order->items->first()->id => 5])->assertSessionHasErrors();

        $this->assertNull($order->fresh()->delivered_at);
        $this->assertSame(3000.0, (float) $order->fresh()->total_amount);
    }

    public function test_acceptance_closes_once_a_payment_exists(): void
    {
        // Goods coming back after money changed hands is a return, refund and
        // all, which the existing return flow handles.
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        ArInvoice::firstOrFail()->update(['amount_paid' => 500, 'balance_due' => 2500]);

        $this->deliver($order, [$order->items->first()->id => 1])
            ->assertSessionHasErrors('accepted_quantities');

        $this->assertSame(3000.0, (float) $order->fresh()->total_amount);
    }

    public function test_a_paid_order_can_still_be_marked_delivered(): void
    {
        // Backfilling a delivery on an order already settled is not a refusal:
        // every line was accepted, so there is nothing to resize.
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        ArInvoice::firstOrFail()->update(['amount_paid' => 3000, 'balance_due' => 0]);

        $this->deliver($order, [$order->items->first()->id => 2])->assertSessionHasNoErrors();

        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_the_sale_is_re_posted_at_the_accepted_amount(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();

        $this->deliver($order, [$order->items->first()->id => 1])->assertSessionHasNoErrors();

        // Net receivable across every posting for this order: the original
        // 3000 debit, its reversal, then a 1500 debit.
        $receivable = \App\Models\JournalEntryLine::whereHas('account', fn ($q) => $q->where('code', '1100'))
            ->get()
            ->sum(fn ($line) => $line->line_type === 'debit' ? (float) $line->amount : -(float) $line->amount);

        $this->assertSame(1500.0, round($receivable, 2));
    }
}
