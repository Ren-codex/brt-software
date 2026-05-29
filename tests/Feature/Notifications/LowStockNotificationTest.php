<?php

namespace Tests\Feature\Notifications;

use App\Models\ListBrand;
use App\Models\ListUnit;
use App\Models\Product;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_has_correct_type_and_channels(): void
    {
        $brand   = ListBrand::create(['name' => 'BrandA']);
        $unit    = ListUnit::create(['name' => 'pcs']);
        $product = Product::create([
            'brand_id'      => $brand->id,
            'pack_size'     => '500mg',
            'unit_id'       => $unit->id,
            'is_active'     => true,
            'minimum_stock' => 10,
        ]);

        $product->load(['brand', 'unit']);
        $notification = new LowStockNotification($product, 3);
        $data = $notification->toDatabase(null);

        $this->assertSame('low_stock', $data['type']);
        $this->assertSame($product->id, $data['product_id']);
        $this->assertSame(3, $data['current_stock']);
        $this->assertSame(10, $data['minimum_stock']);
        $this->assertContains('database', $notification->via(null));
        $this->assertContains('broadcast', $notification->via(null));
        $this->assertSame('low_stock', $notification->broadcastType());
    }
}
