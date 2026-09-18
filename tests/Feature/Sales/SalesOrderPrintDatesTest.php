<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The printed meta bar used to carry a hardcoded "---" for both dates and to
 * read the delivery location from the old dropdown field, so all three cells
 * were blank on nearly every order.
 */
class SalesOrderPrintDatesTest extends TestCase
{
    use RefreshDatabase;

    private function render(array $attributes = []): string
    {
        $order = new SalesOrder(array_merge([
            'so_number' => 'SO-EXT-202609-0002',
            'order_date' => '2026-09-17',
            'payment_mode' => 'Credit Sales',
            'total_amount' => 15700,
            'total_discount' => 150,
            'delivery_location' => 'Gusu',
        ], $attributes));

        $order->setRelation('customer', new Customer(['name' => 'Nora', 'address' => 'Gusu']));
        $order->setRelation('location', null);
        $order->setRelation('salesRep', null);

        return view('prints.sales_order', ['sales_order' => $order, 'items' => collect()])->render();
    }

    public function test_it_prints_both_dates(): void
    {
        $html = $this->render(['shipping_date' => '2026-09-20', 'delivery_date' => '2026-09-22']);

        $this->assertStringContainsString('09/20/2026', $html);
        $this->assertStringContainsString('09/22/2026', $html);
    }

    public function test_it_prints_the_delivery_location_typed_on_the_order(): void
    {
        // Deliberately different from the customer's address, which is printed
        // under Bill To — otherwise the old broken cell would pass this too.
        $html = $this->render(['delivery_location' => 'Barangay Talon-Talon']);

        $this->assertStringContainsString('Barangay Talon-Talon', $html);
    }

    public function test_a_missing_date_still_prints_a_dash(): void
    {
        $html = $this->render(['shipping_date' => '2026-09-20']);

        $this->assertStringContainsString('09/20/2026', $html);
        $this->assertStringContainsString('---', $html);
    }
}
