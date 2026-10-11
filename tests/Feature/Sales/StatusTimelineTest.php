<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * The status timeline says where an order is and what it is waiting on.
 *
 * It is rebuilt from the order's own facts rather than read from a log, so
 * these tests pin that each fact moves the right step.
 */
class StatusTimelineTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    private Employee $driver;

    private Employee $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);

        ListStatus::firstOrCreate(['slug' => 'for-release'], [
            'name' => 'For Release', 'text_color' => '#fff', 'bg_color' => '#007678',
        ]);

        $this->driver = $this->employee('Ana');
        $this->checker = $this->employee('Carl');
    }

    private function employee(string $firstname): Employee
    {
        return Employee::create([
            'firstname' => $firstname, 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);
    }

    private function steps(SalesOrder $order): array
    {
        return collect(
            $this->actingAs($this->user)->getJson('/sales-orders/'.$order->id.'/timeline')->assertOk()->json('steps')
        )->keyBy('key')->all();
    }

    private function codOrder(): SalesOrder
    {
        $this->postCod(['driver_id' => $this->driver->id, 'checker_id' => $this->checker->id])
            ->assertSessionHasNoErrors();

        return SalesOrder::firstOrFail();
    }

    public function test_a_new_cod_order_is_waiting_on_delivery(): void
    {
        $steps = $this->steps($this->codOrder());

        $this->assertSame('done', $steps['placed']['state']);
        // Goods go out before the money comes in on COD.
        $this->assertSame('current', $steps['delivery']['state']);
        $this->assertSame('upcoming', $steps['payment']['state']);
        $this->assertSame('upcoming', $steps['closed']['state']);
        $this->assertStringContainsString('Checker Carl Cruz', $steps['delivery']['detail']);
    }

    public function test_collecting_the_money_marks_payment_done_and_names_who_holds_it(): void
    {
        $order = $this->codOrder();
        $invoice = ArInvoice::firstOrFail();

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $steps = $this->steps($order->fresh());

        $this->assertSame('done', $steps['payment']['state']);
        $this->assertNotSame('done', $steps['turnover']['state']);
        $this->assertStringContainsString('Carl Cruz', $steps['turnover']['detail']);
    }

    public function test_a_cancelled_order_stops_and_skips_what_was_left(): void
    {
        $order = $this->codOrder();
        $cancelled = ListStatus::firstOrCreate(['slug' => 'cancelled'], [
            'name' => 'Cancelled', 'text_color' => '#fff', 'bg_color' => '#dc2626',
        ]);
        $order->update(['status_id' => $cancelled->id, 'cancellation_remarks' => 'Customer changed their mind']);

        $steps = $this->steps($order->fresh());

        $this->assertSame('stopped', $steps['cancelled']['state']);
        $this->assertSame('skipped', $steps['closed']['state']);
        $this->assertStringContainsString('Customer changed their mind', $steps['cancelled']['detail']);
    }
}
