<?php

namespace Tests\Feature\Libraries;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListLocation;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\Remittance;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Modules\RemittanceClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regression guard: today's cash sales/remittances from branches other than the
 * Zamboanga City main office must still count toward the "Undeposited Cash"
 * total on the Cash Management / Remittances page when no location filter is applied.
 */
class RemittanceLocationScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        foreach (['paid', 'liquidated'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name'       => ucwords(str_replace('-', ' ', $slug)),
                'text_color' => '#fff',
                'bg_color'   => '#333',
            ]);
        }

        $this->customer = Customer::create([
            'name'           => 'Test Customer',
            'address'        => 'Addr',
            'contact_number' => '09000000001',
            'added_by_id'    => $this->user->id,
            'is_active'      => true,
            'is_regular'     => false,
            'is_blacklisted' => false,
        ]);
    }

    private function makeLiquidatedRemittance(?int $locationId): Remittance
    {
        $so = SalesOrder::create([
            'so_number'    => 'SO-REM-' . uniqid(),
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'paid')->first()->id,
            'total_amount' => 1000,
            'payment_mode' => 'cash',
            'location_id'  => $locationId,
            'added_by_id'  => $this->user->id,
        ]);

        $invoice = ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => ListStatus::where('slug', 'paid')->first()->id,
            'invoice_number' => 'INV-' . $so->so_number,
            'invoice_date'   => now()->toDateString(),
            'amount_due'     => 1000,
            'amount_paid'    => 1000,
            'balance_due'    => 0,
        ]);

        $receipt = Receipt::create([
            'ar_invoice_id'  => $invoice->id,
            'customer_id'    => $this->customer->id,
            'status_id'      => ListStatus::where('slug', 'liquidated')->first()->id,
            'receipt_number' => 'REC-' . uniqid(),
            'receipt_type'   => 'payment',
            'receipt_date'   => now()->toDateString(),
            'amount_paid'    => 1000,
            'payment_mode'   => 'cash',
        ]);

        $remittance = Remittance::create([
            'remittance_no'   => 'REM-' . uniqid(),
            'remittance_date' => now()->toDateString(),
            'total_amount'    => 1000,
            'summary'         => [],
            'status_id'       => ListStatus::where('slug', 'liquidated')->first()->id,
            'created_by_id'   => $this->user->id,
        ]);

        $receipt->update(['remittance_id' => $remittance->id]);

        return $remittance;
    }

    public function test_undeposited_summary_includes_branch_sales_when_no_location_filter_given(): void
    {
        $mainOffice = ListLocation::create(['name' => 'Zamboanga City', 'is_active' => true]);
        $branch = ListLocation::create(['name' => 'Branch Office', 'is_active' => true]);

        $this->makeLiquidatedRemittance($mainOffice->id);
        $this->makeLiquidatedRemittance($branch->id);

        $request = new Request();
        $request->merge([]); // no location_id -- "All Locations"

        $response = app(RemittanceClass::class)->undepositedSummary($request);
        $data = $response->getData(true);

        $this->assertEquals(2, $data['count'],
            'With no location filter, undeposited summary must include remittances from every location, not just the main office.');
        $this->assertEquals(2000.0, $data['total_amount']);
    }

    public function test_undeposited_summary_still_filters_when_location_explicitly_requested(): void
    {
        $mainOffice = ListLocation::create(['name' => 'Zamboanga City', 'is_active' => true]);
        $branch = ListLocation::create(['name' => 'Branch Office', 'is_active' => true]);

        $this->makeLiquidatedRemittance($mainOffice->id);
        $this->makeLiquidatedRemittance($branch->id);

        $request = new Request();
        $request->merge(['location_id' => $branch->id]);

        $response = app(RemittanceClass::class)->undepositedSummary($request);
        $data = $response->getData(true);

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(1000.0, $data['total_amount']);
    }
}
