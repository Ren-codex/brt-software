<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderIncentive;
use App\Services\Modules\ArInvoiceClass;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Settling an invoice awards the sales rep an incentive, and that row demands a
 * rep — so an order encoded without one threw, and the controller redirected
 * back as though the payment had worked: no receipt, no error, invoice still
 * unpaid.
 *
 * Two separate faults: the incentive assuming a rep, and a failed transaction
 * reported as a success.
 */
class PaymentFailureSurfacingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
    }

    private function pay(array $overrides = [])
    {
        $invoice = ArInvoice::firstOrFail();

        return $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, array_merge([
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Cash',
        ], $overrides));
    }

    public function test_an_order_without_a_sales_rep_can_still_be_paid(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->pay()->assertSessionHasNoErrors();

        $invoice = ArInvoice::firstOrFail();
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertSame('paid', $invoice->status->slug);
        $this->assertSame('closed', SalesOrder::firstOrFail()->status->slug);
        $this->assertSame(0, SalesOrderIncentive::count(), 'No rep, so nobody to credit.');
    }

    public function test_a_failed_payment_is_reported_rather_than_swallowed(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->mock(ArInvoiceClass::class, function ($mock) {
            $mock->shouldReceive('payment')->andThrow(
                new QueryException('mysql', 'insert into receipts', [], new \Exception('NOT NULL constraint failed'))
            );
        });

        $this->pay()->assertSessionHasErrors();
    }
}
