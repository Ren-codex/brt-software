<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * What each driver and rep is still carrying. A city run clears the same
 * afternoon, so anything with days on it is worth a phone call — and an
 * out-of-town order (SO-EXT) legitimately takes longer, which is why the list
 * says which is which.
 */
class FieldCollectionsListTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantRemittanceAccess($this->user);
    }

    private function holder(string $firstname): Employee
    {
        return Employee::create([
            'firstname' => $firstname, 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);
    }

    private function pendingReceipt(Employee $holder, string $receiptDate, string $soNumber = 'SO-202609-0001'): Receipt
    {
        $status = \App\Models\ListStatus::where('slug', 'pending')->firstOrFail();

        $order = \App\Models\SalesOrder::create([
            'so_number' => $soNumber,
            'order_date' => $receiptDate,
            'customer_id' => $this->customer->id,
            'status_id' => \App\Models\ListStatus::where('slug', 'for-payment')->value('id'),
            'payment_mode' => 'COD',
            'total_amount' => 3000, 'total_discount' => 0,
            'added_by_id' => $this->user->id,
        ]);

        $invoice = \App\Models\ArInvoice::create([
            'sales_order_id' => $order->id,
            'invoice_number' => 'AR-'.uniqid(),
            'invoice_date' => $receiptDate,
            'amount_due' => 3000, 'amount_paid' => 3000, 'balance_due' => 0,
            'total_discount' => 0,
            'status_id' => \App\Models\ListStatus::where('slug', 'paid')->value('id'),
        ]);

        return Receipt::create([
            'receipt_number' => 'OR-'.uniqid(),
            'receipt_type' => 'payment',
            'receipt_date' => $receiptDate,
            'amount_paid' => 3000,
            'balance_due' => 0,
            'payment_mode' => 'Cash',
            'status_id' => $status->id,
            'customer_id' => $this->customer->id,
            'ar_invoice_id' => $invoice->id,
            'held_by_employee_id' => $holder->id,
        ]);
    }

    private function rows(): array
    {
        $response = $this->actingAs($this->user)->getJson('/remittances?option=field-collections');
        $response->assertOk();

        return $response->json();
    }

    public function test_it_lists_what_each_person_holds_oldest_first(): void
    {
        $ana = $this->holder('Ana');
        $ben = $this->holder('Ben');
        $this->pendingReceipt($ben, now()->subDay()->toDateString(), 'SO-202609-0002');
        $this->pendingReceipt($ana, now()->subDays(3)->toDateString());

        $rows = $this->rows();

        $this->assertCount(2, $rows);
        $this->assertSame($ana->fullname, $rows[0]['holder']);
        $this->assertSame(3, $rows[0]['days_out']);
        $this->assertEquals(3000, $rows[0]['amount']);
    }

    public function test_an_out_of_town_order_is_flagged(): void
    {
        $this->pendingReceipt($this->holder('Ana'), now()->toDateString(), 'SO-EXT-202609-0001');

        $this->assertTrue($this->rows()[0]['is_external']);
    }

    public function test_a_remitted_receipt_drops_off_the_list(): void
    {
        $receipt = $this->pendingReceipt($this->holder('Ana'), now()->toDateString());

        $remittance = \App\Models\Remittance::create([
            'remittance_no' => 'RM-'.uniqid(),
            'remittance_date' => now()->toDateString(),
            'summary' => [],
            'total_amount' => 3000,
            'status_id' => \App\Models\ListStatus::where('slug', 'pending')->value('id'),
            'created_by_id' => $this->user->id,
        ]);

        $receipt->update(['held_by_employee_id' => null, 'remittance_id' => $remittance->id]);

        $this->assertCount(0, $this->rows());
    }
}
