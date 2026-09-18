<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Modules\ReportClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sales report used to sort orders into cash, credit or "other", where
 * "cash" meant the literal word — so a sale settled by bank transfer, cheque or
 * a split fell into "other" alongside anything unrecognised.
 *
 * COD is its own line, because it is precisely the case where "sold today" and
 * "collected today" are different numbers.
 */
class SalesPaymentTypeBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        ListStatus::firstOrCreate(['slug' => 'for-payment'], [
            'name' => 'For Payment', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
    }

    private function order(string $paymentMode, float $amount): SalesOrder
    {
        $customer = Customer::create([
            'name' => 'ABC Trading', 'address' => 'Zamboanga City',
            'contact_number' => '09170000000', 'is_active' => 1, 'added_by_id' => $this->user->id,
        ]);

        return SalesOrder::create([
            'so_number' => 'SO-'.uniqid(),
            'order_date' => today()->toDateString(),
            'customer_id' => $customer->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->value('id'),
            'payment_mode' => $paymentMode,
            'total_amount' => $amount,
            'total_discount' => 0,
            'added_by_id' => $this->user->id,
        ]);
    }

    private function summary(): array
    {
        $this->actingAs($this->user);

        $report = (new ReportClass)->summary([
            'from' => today()->subDay()->toDateString(),
            'to' => today()->addDay()->toDateString(),
            'day' => today()->toDateString(),
            'limit' => 10,
            'location_id' => null,
            'payment_mode' => 'all',
            'report_type' => 'sales-by-payment-type',
        ]);

        return $report['payment_summary'];
    }

    public function test_cod_is_counted_on_its_own_line(): void
    {
        $this->order('COD', 3000);
        $this->order('Cash', 1000);
        $this->order('Credit Sales', 2000);

        $summary = $this->summary();

        $this->assertSame(3000.0, (float) $summary['cod']->total_sales);
        $this->assertSame(1000.0, (float) $summary['cash']->total_sales);
        $this->assertSame(2000.0, (float) $summary['credit']->total_sales);
    }

    public function test_a_sale_settled_at_the_counter_counts_as_cash_whatever_the_method(): void
    {
        $this->order('Cash', 1000);
        $this->order('Bank Transfer', 500);
        $this->order('Check', 300);
        $this->order('Split', 200);

        $this->assertSame(2000.0, (float) $this->summary()['cash']->total_sales);
    }

    public function test_the_lines_add_up_to_the_grand_total(): void
    {
        $this->order('COD', 3000);
        $this->order('Cash', 1000);
        $this->order('Credit', 2000);

        $summary = $this->summary();

        $this->assertSame(6000.0, (float) $summary['grand_total_sales']);
        $this->assertSame(3, (int) $summary['grand_total_orders']);
    }
}
