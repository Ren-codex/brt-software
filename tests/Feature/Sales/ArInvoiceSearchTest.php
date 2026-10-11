<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Searching and filtering the AR invoice list.
 *
 * The search has to find an invoice by anything on its row — the customer and
 * the sales rep by name included — and it must only ever narrow the list. It
 * used to be chained as a top-level OR, which let a name match slip past the
 * filters applied before it.
 */
class ArInvoiceSearchTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    private Employee $rep;

    private Employee $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);

        $this->rep = $this->employee('Bianca', 'Reyes');
        $this->driver = $this->employee('Ana', 'Cruz');

        // Two invoices: ABC Trading (from the fixture) and Golden Grains.
        $this->postCod(['driver_id' => $this->driver->id, 'sales_rep_id' => $this->rep->id])
            ->assertSessionHasNoErrors();

        $other = Customer::create([
            'name' => 'Golden Grains', 'address' => 'Zamboanga City',
            'contact_number' => '09170000001', 'is_active' => 1,
            'added_by_id' => $this->user->id,
        ]);
        $this->postCod(['driver_id' => $this->driver->id, 'customer_id' => $other->id])
            ->assertSessionHasNoErrors();
    }

    private function employee(string $firstname, string $lastname): Employee
    {
        return Employee::create([
            'firstname' => $firstname, 'lastname' => $lastname, 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);
    }

    private function search(array $params): array
    {
        return $this->actingAs($this->user)
            ->getJson('/ar-invoices?'.http_build_query(['option' => 'lists', 'count' => 20] + $params))
            ->assertOk()
            ->json('data');
    }

    public function test_it_finds_an_invoice_by_customer_name(): void
    {
        $rows = $this->search(['keyword' => 'golden']);

        $this->assertCount(1, $rows);
        $this->assertSame(2, ArInvoice::count());
    }

    public function test_it_finds_an_invoice_by_sales_rep_name(): void
    {
        // First name, last name, and the two together.
        $this->assertCount(1, $this->search(['keyword' => 'Bianca']));
        $this->assertCount(1, $this->search(['keyword' => 'Reyes']));
        $this->assertCount(1, $this->search(['keyword' => 'Bianca Reyes']));
    }

    public function test_it_finds_an_invoice_by_its_number_and_order_number(): void
    {
        $invoice = ArInvoice::with('sales_order')->firstOrFail();

        $this->assertCount(1, $this->search(['keyword' => $invoice->invoice_number]));
        $this->assertCount(1, $this->search(['keyword' => $invoice->sales_order->so_number]));
    }

    public function test_a_search_cannot_widen_the_list_past_another_filter(): void
    {
        // A status no invoice has, plus a name that matches one. The name must
        // not bring the invoice back in.
        $unused = ListStatus::firstOrCreate(['slug' => 'voided'], [
            'name' => 'Voided', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $this->assertCount(0, $this->search(['keyword' => 'golden', 'status' => $unused->slug]));
    }

    public function test_the_status_filter_offers_only_statuses_in_use(): void
    {
        ListStatus::firstOrCreate(['slug' => 'voided'], [
            'name' => 'Voided', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $options = $this->actingAs($this->user)
            ->getJson('/ar-invoices?option=statuses')
            ->assertOk()
            ->json();

        $inUse = ArInvoice::with('status')->get()->pluck('status.slug')->unique()->sort()->values()->all();

        $this->assertSame($inUse, collect($options)->pluck('slug')->sort()->values()->all());
        $this->assertNotContains('voided', collect($options)->pluck('slug')->all());
    }
}
