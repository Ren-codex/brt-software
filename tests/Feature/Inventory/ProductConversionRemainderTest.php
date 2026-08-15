<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\InventoryWeightLoss;
use App\Models\ListBrand;
use App\Models\ListPackaging;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\Series;
use App\Models\User;
use App\Services\Modules\ProductConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductConversionRemainderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduces the real bug: 5 short-weight sacks of a 10kg product
     * (9.5kg actual each, 47.5kg total) converted into a 25kg-per-unit
     * product. 47.5 / 25 = 1.9, floored to 1 output unit consuming 25kg,
     * leaving a 22.5kg remainder — more than two whole 10kg source sacks.
     * The old code only ever returned a remainder smaller than one source
     * sack; anything larger silently vanished from the books.
     */
    public function test_remainder_larger_than_one_source_sack_is_fully_returned(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $brand = ListBrand::create(['name' => 'Test Brand']);
        $unit = ListUnit::create(['name' => 'Sack']);
        $packaging = ListPackaging::create(['name' => 'Sack']);

        $sourceProduct = Product::create([
            'code' => 'SRC10', 'weight' => 10, 'unit_id' => $unit->id,
            'packaging_id' => $packaging->id, 'is_active' => 1, 'minimum_stock' => 1, 'brand_id' => $brand->id,
        ]);
        $targetProduct = Product::create([
            'code' => 'TGT25', 'weight' => 25, 'unit_id' => $unit->id,
            'packaging_id' => $packaging->id, 'is_active' => 1, 'minimum_stock' => 1, 'brand_id' => $brand->id,
        ]);

        Series::create([
            'name' => 'Batch Code', 'slug' => 'batch_code', 'prefix' => 'B',
            'current_date' => now()->format('Y'), 'starting_value' => 1, 'max_digit' => 6,
        ]);

        $source = InventoryStocks::create([
            'batch_code' => 'B-TEST-SRC', 'product_id' => $sourceProduct->id,
            'quantity' => 1000, 'retail_price' => 530, 'wholesale_price' => 520, 'unit_cost' => 500,
        ]);

        $weightLoss = InventoryWeightLoss::create([
            'inventory_stock_id' => $source->id, 'affected_sacks' => 5, 'loss_per_sack' => 0.5,
            'loss_kg' => 2.5, 'reason' => 'Measurement shortage', 'recorded_by_id' => $user->id, 'recorded_at' => now(),
        ]);

        $request = new Request([
            'source_stock_id' => $source->id,
            'product_id' => $targetProduct->id,
            'weight_loss_ids' => [$weightLoss->id],
            'retail_price' => 1280,
            'wholesale_price' => 1270,
            'unit_cost' => 1250,
        ]);

        $result = app(ProductConversionService::class)->store($request);

        $this->assertTrue($result['status']);
        $this->assertEquals(1, $result['data']['output_quantity']);

        // 1000 - 5 (converted out) + 2 (whole sacks returned) + 1 (short-weight
        // sack returned) = 998 — all 22.5kg of the remainder accounted for.
        $source->refresh();
        $this->assertEquals(998, $source->quantity);

        $returnedShortSack = InventoryWeightLoss::where('inventory_stock_id', $source->id)
            ->where('id', '!=', $weightLoss->id)
            ->first();
        $this->assertNotNull($returnedShortSack, 'Expected a new short-weight sack for the 2.5kg sub-sack remainder.');
        $this->assertEquals(1, $returnedShortSack->affected_sacks);
        $this->assertEqualsWithDelta(7.5, (float) $returnedShortSack->loss_per_sack, 0.01);

        $adjustment = InventoryAdjustment::where('inventory_stocks_id', $source->id)
            ->where('type', 'conversion_partial')
            ->first();
        $this->assertNotNull($adjustment);
        $this->assertStringContainsString('2 full sack(s)', $adjustment->reason);
        $this->assertStringContainsString('2.5 kg partial sack', $adjustment->reason);
    }
}
