<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * The For Turnover row makes itself when it is missing.
 *
 * It arrived after the original status list, and that list is a seeder which
 * deletes the whole table before rebuilding it from fixed ids — so it cannot
 * be re-run on a live database. Rather than leave the feature switched off
 * until somebody ssh'd into the server and seeded one row, the status is
 * created the first time an order needs it.
 *
 * Without this the order still behaves safely — it closes on collection, the
 * way it did before For Turnover existed — but the step is invisible.
 */
class ForTurnoverStatusSelfHealsTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);

        // A database that has never been seeded with it, as production is.
        ListStatus::where('slug', 'for-turnover')->delete();
    }

    private function deliverAndCollect(): SalesOrder
    {
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        return $order->fresh('status');
    }

    public function test_a_cod_collection_creates_the_status_it_needs(): void
    {
        $this->assertFalse(ListStatus::where('slug', 'for-turnover')->exists());

        $this->postCod()->assertSessionHasNoErrors();
        $order = $this->deliverAndCollect();

        $this->assertTrue(ListStatus::where('slug', 'for-turnover')->exists());
        $this->assertSame('for-turnover', $order->status->slug);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_it_is_created_once_not_once_per_order(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->deliverAndCollect();

        $this->postCod()->assertSessionHasNoErrors();
        SalesOrder::latest('id')->firstOrFail();

        $this->assertSame(1, ListStatus::where('slug', 'for-turnover')->count());
    }

    public function test_the_row_it_makes_matches_the_seeder(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->deliverAndCollect();

        $made = ListStatus::where('slug', 'for-turnover')->firstOrFail();
        $this->assertSame('For Turnover', $made->name);
        $this->assertSame('#b0702a', $made->bg_color);
    }

    public function test_nothing_else_conjures_a_status(): void
    {
        // Only For Turnover self-heals. A missing status anywhere else still
        // leaves the order where it is rather than inventing a row.
        ListStatus::where('slug', 'for-release')->delete();

        $this->postCod([
            'payment_mode' => 'Cash', 'delivery_date' => now()->addDays(2)->toDateString(),
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(ListStatus::where('slug', 'for-release')->exists());
    }
}
