<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
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

    private function render(?string $packaging = 'Sack'): string
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

        $item = new SalesOrderItem([
            'batch_code' => 'B2026000007',
            'quantity' => 3,
            'price' => 1250,
            'discount_per_unit' => 0,
        ]);
        $item->setRelation('product', $product->fresh()->load('brand', 'unit', 'packaging'));

        return view('prints.sales_order', [
            'sales_order' => $order,
            'items' => collect([$item]),
        ])->render();
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
