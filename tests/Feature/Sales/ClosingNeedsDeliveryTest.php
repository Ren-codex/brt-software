<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListPosition;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Closed used to mean paid, so an order could read finished while the sacks
 * were still in the warehouse. An order being delivered now has to be true too.
 *
 * A counter sale is the exception that proves it: nobody delivers anything, the
 * customer carries the goods out as they pay, so paying stamps the delivery.
 */
class ClosingNeedsDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        ListStatus::firstOrCreate(['slug' => 'for-release'], [
            'name' => 'For Release', 'text_color' => '#fff', 'bg_color' => '#007678',
        ]);
        $this->grantArInvoiceAccess($this->user);
    }

    private function driver(): Employee
    {
        $position = ListPosition::firstOrCreate(
            ['title' => 'Driver 1 Wingvan'],
            ['slug' => 'driver-1-wingvan', 'rate_per_day' => 500]
        );

        return Employee::create([
            'firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'position_id' => $position->id,
        ]);
    }

    private function cashPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            // A counter sale has neither: the customer carries it out.
            'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ], $overrides));
    }

    public function test_a_counter_sale_closes_and_stamps_its_own_delivery(): void
    {
        // No driver, no delivery date: the customer carries it out as they pay.
        $this->actingAs($this->user)->post('/sales-orders', $this->cashPayload())
            ->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertSame('closed', $order->status->slug);
        $this->assertNotNull($order->delivered_at);
    }

    public function test_a_paid_delivery_waits_for_release_rather_than_closing(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->cashPayload(['driver_id' => $this->driver()->id]))
            ->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertSame('for-release', $order->status->slug);
        $this->assertNull($order->delivered_at);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_marking_it_delivered_then_closes_it(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->cashPayload(['driver_id' => $this->driver()->id]))
            ->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, ['action' => 'mark-delivered'])
            ->assertSessionHasNoErrors();

        $this->assertSame('closed', $order->fresh()->status->slug);
    }

    public function test_an_undelivered_order_still_owing_money_stays_for_payment(): void
    {
        // Nothing paid yet, so For Release would be a lie in the other direction.
        $this->postCod(['driver_id' => $this->driver()->id])->assertSessionHasNoErrors();

        $this->assertSame('for-payment', SalesOrder::firstOrFail()->status->slug);
    }

    public function test_collecting_a_cod_delivery_closes_it_because_the_goods_went_out(): void
    {
        $driver = $this->driver();
        $rep = Employee::create([
            'firstname' => 'Bea', 'lastname' => 'Cruz', 'mobile' => '09170000001',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);

        $this->postCod(['driver_id' => $driver->id, 'sales_rep_id' => $rep->id])
            ->assertSessionHasNoErrors();

        $invoice = ArInvoice::firstOrFail();
        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertNotNull($order->delivered_at);
        $this->assertSame('closed', $order->status->slug);
    }
}
