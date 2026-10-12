<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Money that has not arrived proves nothing about a delivery.
 *
 * A COD collection recorded from the AR Invoices screen fills in a delivery
 * nobody marked, and rightly so: the cash came back, so the sacks went out.
 * A cheque the bank has not paid, or a transfer nobody has confirmed, says no
 * such thing — the truck may still be in the yard.
 *
 * Stamping it anyway costs more than a wrong date. The delivery leaves the
 * driver's list, and that list is the only place Mark Delivered exists, so
 * what actually arrived — the date, the quantities kept, anything refused —
 * can never be recorded. The COD rule goes with it: both it and the delivery
 * approval only run on an order that is not yet stamped.
 */
class CodStampNeedsClearedMoneyTest extends TestCase
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

    private function pay(array $split)
    {
        $invoice = ArInvoice::firstOrFail();

        return $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'payment_date' => now()->toDateString(),
            'balance_due' => (float) $invoice->fresh()->balance_due,
            'splits' => [$split],
        ]);
    }

    private function payByCheque()
    {
        return $this->pay([
            'payment_mode' => 'Check',
            'amount' => 3000,
            'bank_account_id' => null,
            'reference_number' => 'CHK-'.uniqid(),
            'check_date' => now()->addDays(10)->toDateString(),
        ]);
    }

    /** The case the stamp exists for: cash is back, so the goods are gone. */
    public function test_a_cash_collection_still_stamps_the_delivery(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->pay(['payment_mode' => 'Cash', 'amount' => 3000])->assertSessionHasNoErrors();

        $this->assertNotNull(SalesOrder::firstOrFail()->delivered_at);
    }

    public function test_an_uncleared_cheque_does_not(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->payByCheque()->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertNull($order->delivered_at);
        // Nothing was recognized either, so the invoice still shows the lot.
        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_the_order_stays_on_the_drivers_list(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->payByCheque()->assertSessionHasNoErrors();

        $board = $this->actingAs($this->user)
            ->getJson('/sales-orders?option=delivery-board')
            ->assertOk()
            ->json();

        $soNumber = SalesOrder::firstOrFail()->so_number;
        $this->assertSame([$soNumber], array_column($board['out_for_delivery'], 'reference'));
        // Not waiting on a collection: it is waiting to be delivered.
        $this->assertSame([], array_column($board['to_collect'], 'reference'));
    }

    /**
     * The guard only runs on an order with no delivery stamp, so a premature
     * stamp is a way round it: nothing collected, and the goods go out anyway.
     */
    public function test_the_cod_rule_still_applies_after_a_cheque_is_recorded(): void
    {
        config(['sales.delivery_approval' => true]);

        $this->postCod()->assertSessionHasNoErrors();
        $this->payByCheque()->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, ['action' => 'mark-delivered'])
            ->assertSessionHasErrors('collected_amount');

        $this->assertNull($order->fresh()->delivered_at);
        $this->assertFalse($order->fresh()->awaitsDeliveryApproval());
    }
}
