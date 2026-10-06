<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListBrand;
use App\Models\ListPackaging;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A product is named for its brand and the weight it comes in — "Jasmine Rice
 * 25 Kg" — and is sold by the sack. The print dropped the Kg from the name and
 * then gave it a column of its own labelled Unit of Measurement, so the sack
 * the order is actually counted in never appeared anywhere.
 */
class SalesOrderPrintItemsTest extends TestCase
{
    use RefreshDatabase;

    private function order(): SalesOrder
    {
        $order = new SalesOrder([
            'so_number' => 'SO-EXT-202610-0001',
            'order_date' => '2026-10-07',
            'payment_mode' => 'Credit Sales',
            'total_amount' => 3750,
            'total_discount' => 0,
            'delivery_location' => 'Gusu',
        ]);
        $order->setRelation('customer', new Customer(['name' => 'Nora', 'address' => 'Gusu']));
        $order->setRelation('location', null);
        $order->setRelation('salesRep', null);
        $order->setRelation('driver', null);

        return $order;
    }

    private function item(?string $packaging = 'Sack'): SalesOrderItem
    {
        $brand = ListBrand::create(['name' => 'Jasmine Rice', 'is_active' => 1]);

        $product = Product::create([
            'code' => 'JR-25',
            'brand_id' => $brand->id,
            'weight' => 25,
            'unit_id' => ListUnit::create(['name' => 'Kg', 'is_active' => 1])->id,
            'packaging_id' => $packaging ? ListPackaging::create(['name' => $packaging, 'is_active' => 1])->id : null,
            'is_active' => 1,
        ]);

        $item = new SalesOrderItem([
            'batch_code' => 'B2026000007',
            'quantity' => 3,
            'price' => 1250,
            'discount_per_unit' => 0,
        ]);
        $item->setRelation('product', $product->fresh()->load('brand', 'unit', 'packaging'));

        return $item;
    }

    private function renderOrder(SalesOrder $order, ?SalesOrderItem $item = null, int $items = 1): string
    {
        $one = $item ?? $this->item();
        $rows = collect(range(1, $items))->map(function () use ($one) {
            $copy = $one->replicate();
            $copy->setRelation('product', $one->product);

            return $copy;
        });

        return view('prints.sales_order', [
            'sales_order' => $order,
            'items' => $rows,
        ])->render();
    }

    private function render(?string $packaging = 'Sack'): string
    {
        return $this->renderOrder($this->order(), $this->item($packaging));
    }

    private function itemCells(string $html): array
    {
        preg_match('/<table class="items-table">(.*?)<\/table>/s', $html, $table);
        preg_match('/<tbody>(.*?)<\/tbody>/s', $table[1], $body);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $body[1], $cells);

        return array_map(
            fn ($cell) => trim(preg_replace('/\s+/', ' ', strip_tags($cell))),
            $cells[1]
        );
    }

    public function test_the_product_is_named_with_the_weight_it_comes_in(): void
    {
        $cells = $this->itemCells($this->render());

        $this->assertSame('Jasmine Rice 25 Kg', $cells[1], 'The Kg belongs to the name.');
    }

    public function test_the_column_shows_how_it_is_packed(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<th class="text-center">Packaging</th>', $html);
        $this->assertSame('Sack', $this->itemCells($html)[3]);
        $this->assertStringNotContainsString('Unit of Measurement', $html);
    }

    public function test_a_product_with_no_packaging_recorded_prints_a_dash(): void
    {
        // Rather than an empty cell that reads as a rendering fault.
        $this->assertSame('---', $this->itemCells($this->render(packaging: null))[3]);
    }

    public function test_it_names_the_driver_beside_the_sales_rep(): void
    {
        $order = $this->order();
        $order->setRelation('driver', new Employee(['firstname' => 'Jane', 'lastname' => 'Reyes']));

        $html = $this->renderOrder($order);

        $this->assertStringContainsString('<strong>Driver:</strong>', $html);
        $this->assertStringContainsString('Reyes', $html);
    }

    public function test_an_order_nobody_is_driving_prints_a_dash(): void
    {
        // A counter sale has no driver, and an empty box reads as a fault.
        $order = $this->order();
        $order->setRelation('driver', null);

        preg_match('/<strong>Driver:<\/strong>([^<]*)</', $this->renderOrder($order), $m);
        $this->assertSame('---', trim($m[1]));
    }

    public function test_the_first_copy_takes_exactly_half_the_sheet(): void
    {
        // The sheet is cut in half with scissors, so the line has to land on
        // the paper's midpoint rather than wherever the content happens to end.
        // A4 is 297mm, the page margin takes 6mm off each end: 6 + 142.5 = 148.5.
        $html = $this->render();

        $this->assertStringContainsString('.half-sheet-top { height: 142.5mm;', $html);
        $this->assertStringContainsString('class="copy-cell half-sheet-top"', $html);
    }

    public function test_four_items_still_share_one_sheet(): void
    {
        // Four fits: one copy stands 133.7mm against the 142.5mm half-sheet.
        $html = $this->renderOrder($this->order(), items: 4);

        $this->assertStringContainsString('class="copy-cell half-sheet-top"', $html);
        $this->assertStringNotContainsString('class="copy-cell new-sheet"', $html);
    }

    public function test_five_items_take_a_sheet_each(): void
    {
        // A fifth row comes within 2.1mm of the cut line, close enough that one
        // wrapped product name would cross it — so neither copy is cut through.
        $html = $this->renderOrder($this->order(), items: 5);

        // The class name also appears in the stylesheet, so match where it is
        // applied rather than merely mentioned.
        $this->assertStringNotContainsString('class="copy-cell half-sheet-top"', $html);
        $this->assertStringContainsString('class="copy-cell new-sheet"', $html);
    }

    public function test_the_totals_block_shows_the_total_and_nothing_else(): void
    {
        // Subtotal and Discount restated the same figure twice over on an
        // order that rarely carries a discount at all.
        $html = $this->render();

        preg_match('/<td style="width: 220px;">(.*?)<\/td>\s*<\/tr>/s', $html, $block);
        $totals = trim(preg_replace('/\s+/', ' ', strip_tags($block[1])));

        $this->assertSame('TOTAL PHP 3,750.00', $totals);
    }
}
