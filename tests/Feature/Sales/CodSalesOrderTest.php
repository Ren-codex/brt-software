<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\InventoryStocks;
use App\Models\JournalEntryLine;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductConversion;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Cash on delivery: the goods go out before the money comes in, so the sale is
 * receivable until the driver returns with the cash — not a cash sale, which
 * would book collected money and close the order before anyone has collected
 * anything.
 *
 * It is still not credit: nothing is deferred beyond the handover, so it needs
 * no supervisor and no credit limit, and it is due on the delivery date.
 */
class CodSalesOrderTest extends TestCase
{
    use RefreshDatabase;

    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
    }

    public function test_a_cod_order_needs_no_supervisor_even_when_delivery_is_days_away(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_cod_order_is_owed_until_it_is_collected(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $invoice = ArInvoice::firstOrFail();
        $this->assertSame(3000.0, (float) $invoice->balance_due);
        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertSame('unpaid', $invoice->status->slug);
        $this->assertFalse($invoice->receipts()->exists(), 'No receipt should exist before anyone collects.');
    }

    public function test_a_cod_order_stays_open_rather_than_closing_on_save(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->assertSame('for-payment', SalesOrder::firstOrFail()->status->slug);
    }

    public function test_a_cod_sale_is_booked_as_receivable_not_as_collected_cash(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $debitedCodes = JournalEntryLine::where('line_type', 'debit')
            ->with('account')
            ->get()
            ->pluck('account.code')
            ->all();

        $this->assertContains('1100', $debitedCodes, 'COD should debit Accounts Receivable.');
        $this->assertNotContains('1050', $debitedCodes, 'COD must not book Undeposited Collections before collection.');
    }

    public function test_the_due_date_follows_the_delivery_date(): void
    {
        $delivery = now()->addDays(3)->toDateString();

        // Even if the browser posts something else, delivery day is when COD is due.
        $this->postCod(['delivery_date' => $delivery, 'due_date' => now()->addDays(30)->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame($delivery, SalesOrder::firstOrFail()->due_date->toDateString());
    }

    public function test_cod_requires_a_delivery_date(): void
    {
        $this->postCod(['delivery_date' => null])->assertSessionHasErrors('delivery_date');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_a_credit_limit_does_not_block_a_cod_order(): void
    {
        // Nothing is extended: the customer pays the driver at the door.
        $this->customer->update(['credit_limit' => 1]);

        $this->postCod()->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }
}
