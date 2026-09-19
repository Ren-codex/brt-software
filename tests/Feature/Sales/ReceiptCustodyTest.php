<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListPosition;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Money collected at the door is not in the office yet. Until a remittance is
 * counted it sits with a named person — the driver who took it — and that is
 * the difference between "not collected" and "someone is carrying your cash".
 */
class ReceiptCustodyTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
    }

    private function employee(string $firstname, string $positionTitle): Employee
    {
        $position = ListPosition::firstOrCreate(
            ['title' => $positionTitle],
            ['slug' => \Str::slug($positionTitle), 'rate_per_day' => 500]
        );

        return Employee::create([
            'firstname' => $firstname, 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'position_id' => $position->id,
        ]);
    }

    private function driver(): Employee
    {
        return $this->employee('Ana', 'Driver 1 Wingvan');
    }

    /**
     * Settling an order awards its rep an incentive, so the order needs one —
     * and remittance scopes to the rep's own receipts, so that rep is the
     * logged-in user.
     */
    private function codOrderWithDriver(Employee $driver): SalesOrder
    {
        $rep = $this->employee('Bea', 'Sales Rep');
        $rep->update(['user_id' => $this->user->id]);

        $this->postCod(['driver_id' => $driver->id, 'sales_rep_id' => $rep->id])
            ->assertSessionHasNoErrors();

        return SalesOrder::firstOrFail();
    }

    private function collect(SalesOrder $order, float $amount = 3000): void
    {
        $invoice = ArInvoice::firstOrFail();

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id.'?option=payment', [
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Cash',
        ])->assertSessionHasNoErrors();
    }

    public function test_a_field_collection_is_held_by_the_orders_driver(): void
    {
        $driver = $this->driver();
        $order = $this->codOrderWithDriver($driver);

        $this->collect($order);

        $this->assertSame($driver->id, Receipt::firstOrFail()->held_by_employee_id);
    }

    public function test_remitting_clears_the_holder(): void
    {
        // Handed in: nobody is carrying it any more.
        $driver = $this->driver();
        $order = $this->codOrderWithDriver($driver);
        $this->collect($order);

        $receipt = Receipt::firstOrFail();
        $this->assertSame($driver->id, $receipt->held_by_employee_id);

        $this->grantRemittanceAccess($this->user);
        // The remittance number comes from the series table, which the test
        // database has no row for.
        \App\Models\Series::firstOrCreate(['slug' => 'remittance'], [
            'name' => 'Remittance', 'prefix' => 'RM-', 'starting_value' => 1, 'max_digit' => 4,
        ]);

        $this->actingAs($this->user)->post('/remittances', [
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, \App\Models\Remittance::count(), 'The remittance itself should exist.');
        $this->assertNull($receipt->fresh()->held_by_employee_id);
    }

    public function test_turning_over_moves_the_money_to_its_new_holder(): void
    {
        $driver = $this->driver();
        $order = $this->codOrderWithDriver($driver);
        $this->collect($order);
        $receipt = Receipt::firstOrFail();
        $rep = Employee::where('firstname', 'Bea')->firstOrFail();

        $this->grantReceiptAccess($this->user);

        $this->actingAs($this->user)
            ->put('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $rep->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($rep->id, $receipt->fresh()->held_by_employee_id);
    }

    public function test_the_handover_is_recorded_in_the_activity_log(): void
    {
        // Who had the money and when is the whole point of tracking custody.
        $driver = $this->driver();
        $order = $this->codOrderWithDriver($driver);
        $this->collect($order);
        $receipt = Receipt::firstOrFail();
        $rep = Employee::where('firstname', 'Bea')->firstOrFail();

        $this->grantReceiptAccess($this->user);
        $this->actingAs($this->user)
            ->put('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $rep->id]);

        $logged = \Spatie\Activitylog\Models\Activity::where('subject_type', Receipt::class)
            ->where('subject_id', $receipt->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($logged, 'The handover should leave a trail.');
        $this->assertSame($rep->id, (int) data_get($logged->properties, 'attributes.held_by_employee_id'));
        $this->assertSame($driver->id, (int) data_get($logged->properties, 'old.held_by_employee_id'));
    }

    public function test_a_remitted_receipt_cannot_be_turned_over(): void
    {
        $driver = $this->driver();
        $order = $this->codOrderWithDriver($driver);
        $this->collect($order);
        $receipt = Receipt::firstOrFail();

        $remittance = \App\Models\Remittance::create([
            'remittance_no' => 'RM-'.uniqid(),
            'remittance_date' => now()->toDateString(),
            'summary' => [],
            'total_amount' => 3000,
            'status_id' => \App\Models\ListStatus::where('slug', 'pending')->value('id'),
            'created_by_id' => $this->user->id,
        ]);
        $receipt->update(['held_by_employee_id' => null, 'remittance_id' => $remittance->id]);

        $this->grantReceiptAccess($this->user);

        $this->actingAs($this->user)
            ->put('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $driver->id])
            ->assertSessionHasErrors();
    }

    public function test_recording_a_cod_collection_stamps_the_delivery(): void
    {
        // Money cannot come back unless the goods went out.
        $order = $this->codOrderWithDriver($this->driver());
        $this->assertNull($order->delivered_at);

        $this->collect($order);

        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_an_existing_delivery_stamp_is_not_moved_by_a_collection(): void
    {
        $order = $this->codOrderWithDriver($this->driver());
        $order->update(['delivered_at' => now()->subDays(3)]);

        $this->collect($order);

        $this->assertTrue($order->fresh()->delivered_at->isSameDay(now()->subDays(3)));
    }
}
