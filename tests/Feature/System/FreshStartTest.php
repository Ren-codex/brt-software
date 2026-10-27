<?php

namespace Tests\Feature\System;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InventoryStocks;
use App\Models\JournalEntry;
use App\Models\ListPosition;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Emptying the books before the business starts using them for real.
 *
 * The line this draws is between what the business *is* and what it has
 * *done*. Customers, products, employees, bank accounts and the chart of
 * accounts are the first; orders, stock, receipts and journal entries are the
 * second, and only the second goes.
 *
 * Getting that line wrong in either direction is expensive: too far and
 * somebody re-types the customer list, not far enough and the new books open
 * with last month's figures still in them.
 */
class FreshStartTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);

        // A day's trading to throw away.
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered', 'collected_amount' => 3000, 'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        // A backup from today, which the command insists on.
        $dir = storage_path('app/BRT');
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/test-backup.zip', 'not really a zip');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/BRT/test-backup.zip'));
        @rmdir(storage_path('app/BRT'));
        parent::tearDown();
    }

    public function test_it_empties_what_the_business_has_done(): void
    {
        $this->assertGreaterThan(0, SalesOrder::count());
        $this->assertGreaterThan(0, ArInvoice::count());
        $this->assertGreaterThan(0, Receipt::count());
        $this->assertGreaterThan(0, JournalEntry::count());

        $this->artisan('system:fresh-start --yes')->assertSuccessful();

        $this->assertSame(0, SalesOrder::count());
        $this->assertSame(0, ArInvoice::count());
        $this->assertSame(0, Receipt::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, DB::table('journal_entry_lines')->count());
        $this->assertSame(0, DB::table('sales_order_items')->count());
        $this->assertSame(0, InventoryStocks::count());
    }

    public function test_it_keeps_what_the_business_is(): void
    {
        $customers = Customer::count();
        $products = Product::count();
        $employees = Employee::count();
        $positions = ListPosition::count();

        $this->artisan('system:fresh-start --yes')->assertSuccessful();

        $this->assertSame($customers, Customer::count());
        $this->assertSame($products, Product::count());
        $this->assertSame($employees, Employee::count());
        $this->assertSame($positions, ListPosition::count());
        $this->assertGreaterThan(0, DB::table('users')->count());
        $this->assertGreaterThan(0, DB::table('list_statuses')->count());
        $this->assertGreaterThan(0, DB::table('accounts')->count(), 'The chart of accounts is not a transaction.');
    }

    public function test_it_will_not_run_without_a_backup_from_today(): void
    {
        @unlink(storage_path('app/BRT/test-backup.zip'));

        $this->artisan('system:fresh-start --yes')->assertFailed();

        // And nothing was touched.
        $this->assertGreaterThan(0, SalesOrder::count());
    }

    public function test_an_old_backup_does_not_count(): void
    {
        touch(storage_path('app/BRT/test-backup.zip'), now()->subDays(3)->timestamp);

        $this->artisan('system:fresh-start --yes')->assertFailed();

        $this->assertGreaterThan(0, SalesOrder::count());
    }

    public function test_typing_the_wrong_database_name_deletes_nothing(): void
    {
        $orders = SalesOrder::count();

        $this->artisan('system:fresh-start')
            ->expectsQuestion('Type the database name to confirm ('.DB::connection()->getDatabaseName().')', 'not-the-database')
            ->assertSuccessful();

        $this->assertSame($orders, SalesOrder::count());
    }

    public function test_document_numbers_start_again(): void
    {
        DB::table('series')->insert([
            'name' => 'Sales Order', 'slug' => 'so_no', 'prefix' => 'SO',
            'starting_value' => 417, 'max_digit' => 6,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('system:fresh-start --yes')->assertSuccessful();

        // The counter is reset, and the row survives — the service reads it.
        $this->assertSame(1, (int) DB::table('series')->where('slug', 'so_no')->value('starting_value'));
    }

    public function test_the_petty_cash_fund_is_emptied_but_kept(): void
    {
        DB::table('petty_cash_funds')->insert([
            'name' => 'Office Fund', 'gl_code' => '1060', 'balance' => 7500,
            'fixed_amount' => 10000, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('system:fresh-start --yes')->assertSuccessful();

        $fund = DB::table('petty_cash_funds')->where('name', 'Office Fund')->first();
        $this->assertNotNull($fund, 'The fund is a thing the business has.');
        $this->assertSame(0.0, (float) $fund->balance, 'Its balance is something it did.');
    }
}
