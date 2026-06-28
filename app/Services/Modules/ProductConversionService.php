<?php

namespace App\Services\Modules;

use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\InventoryWeightLoss;
use App\Models\Product;
use App\Models\ProductConversion;
use App\Services\Accounting\JournalEntryService;
use App\Services\SeriesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProductConversionService
{
    public function __construct(
        private SeriesService $series,
        private JournalEntryService $journalEntryService,
    ) {}

    public function store($request): array
    {
        $source = InventoryStocks::with('receivedItem.product', 'product')->findOrFail($request->source_stock_id);

        // Validate quantity
        if ($source->quantity < $request->source_qty_used) {
            throw ValidationException::withMessages([
                'source_qty_used' => ['Not enough stock. Available: ' . $source->quantity . ' units.'],
            ]);
        }

        // Validate conversion ratio
        if (empty($request->conversion_ratio) || floatval($request->conversion_ratio) <= 0) {
            throw ValidationException::withMessages([
                'conversion_ratio' => ['Conversion ratio must be greater than zero.'],
            ]);
        }

        // Validate target product exists and is active
        $targetProduct = Product::find($request->product_id);
        if (!$targetProduct || !$targetProduct->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product is inactive or does not exist.'],
            ]);
        }

        $outputQty = (int) round($request->source_qty_used * $request->conversion_ratio);

        if ($outputQty < 1) {
            throw ValidationException::withMessages([
                'conversion_ratio' => ['Conversion ratio results in zero output units.'],
            ]);
        }

        $newBatchCode = $this->series->get('batch_code');

        // Use frontend-computed unit cost if provided, otherwise derive from source
        $sourceUnitCost = floatval($source->receivedItem?->unit_cost ?? $source->unit_cost ?? 0);
        $sourceWeight   = floatval($source->receivedItem?->product?->weight ?? $source->product?->weight ?? 0);
        $targetWeight   = floatval($targetProduct->weight ?? 0);
        $unitCostPerKg  = $sourceWeight > 0 ? $sourceUnitCost / $sourceWeight : 0;
        $derivedCost    = $unitCostPerKg * $targetWeight;
        $outputUnitCost = floatval($request->unit_cost ?? 0) > 0
            ? floatval($request->unit_cost)
            : $derivedCost;

        // In short weight mode: compute remainder from selected weight losses server-side
        $computedRemainderKg = 0.0;
        $selectedLossIds     = $request->weight_loss_ids ?? [];

        if (!empty($selectedLossIds) && $sourceWeight > 0 && $targetWeight > 0) {
            $selectedLosses  = InventoryWeightLoss::whereIn('id', $selectedLossIds)
                ->where('inventory_stock_id', $source->id)
                ->get();
            $selectedTotalKg = $selectedLosses->sum(
                fn ($wl) => ($wl->affected_sacks ?? 0) * ($sourceWeight - ($wl->loss_per_sack ?? 0))
            );
            $computedRemainderKg = fmod($selectedTotalKg, $targetWeight);
        }

        $conversion = ProductConversion::create([
            'source_stock_id'  => $source->id,
            'output_stock_id'  => null,
            'source_qty_used'  => $request->source_qty_used,
            'conversion_ratio' => $request->conversion_ratio,
            'output_quantity'  => $outputQty,
            'reason'           => $request->reason,
            'converted_by_id'  => Auth::id(),
            'conversion_date'  => now()->format('Y-m-d'),
        ]);

        $outputStock = InventoryStocks::create([
            'product_id'      => $request->product_id,
            'conversion_id'   => $conversion->id,
            'batch_code'      => $newBatchCode,
            'quantity'        => $outputQty,
            'unit_cost'       => $outputUnitCost,
            'retail_price'    => $request->retail_price  ?? 0,
            'wholesale_price' => $request->wholesale_price ?? 0,
            'expiration_date' => $request->expiration_date ?? null,
        ]);

        $conversion->update(['output_stock_id' => $outputStock->id]);

        $this->journalEntryService->recordStockConversionEntry($conversion->fresh([
            'sourceStock.receivedItem.product',
            'sourceStock.product',
            'outputStock.product',
        ]));

        // Soft-mark selected weight losses as converted
        if (!empty($selectedLossIds)) {
            InventoryWeightLoss::whereIn('id', $selectedLossIds)
                ->where('inventory_stock_id', $source->id)
                ->update([
                    'converted_at'    => now(),
                    'converted_by_id' => Auth::id(),
                    'conversion_id'   => $conversion->id,
                ]);
        }

        // Decrement source
        $prevQty = $source->quantity;
        $source->decrement('quantity', $request->source_qty_used);

        InventoryAdjustment::create([
            'inventory_stocks_id' => $source->id,
            'previous_quantity'   => $prevQty,
            'new_quantity'        => $source->quantity,
            'reason'              => 'Conversion out → ' . $newBatchCode . ($request->reason ? ' — ' . $request->reason : ''),
            'type'                => 'conversion_out',
            'adjustment_date'     => now()->format('Y-m-d'),
            'adjusted_by_id'      => Auth::id(),
        ]);

        // Return partial sack to source batch as short weight (same batch, no new batch number)
        if ($computedRemainderKg > 0.001 && $sourceWeight > $computedRemainderKg) {
            $lossPerSack        = round($sourceWeight - $computedRemainderKg, 4);
            $prevAfterDecrement = $source->quantity;
            $source->increment('quantity', 1);

            InventoryWeightLoss::create([
                'inventory_stock_id' => $source->id,
                'affected_sacks'     => 1,
                'loss_per_sack'      => $lossPerSack,
                'loss_kg'            => $lossPerSack,
                'reason'             => 'Partial sack remaining from conversion — ' . round($computedRemainderKg, 2) . ' kg',
                'recorded_by_id'     => Auth::id(),
                'recorded_at'        => now(),
            ]);

            InventoryAdjustment::create([
                'inventory_stocks_id' => $source->id,
                'previous_quantity'   => $prevAfterDecrement,
                'new_quantity'        => $source->quantity,
                'reason'              => 'Partial sack (' . round($computedRemainderKg, 2) . ' kg) returned to source after conversion',
                'type'                => 'conversion_partial',
                'adjustment_date'     => now()->format('Y-m-d'),
                'adjusted_by_id'      => Auth::id(),
            ]);
        }

        InventoryAdjustment::create([
            'inventory_stocks_id' => $outputStock->id,
            'previous_quantity'   => 0,
            'new_quantity'        => $outputQty,
            'reason'              => 'Conversion in from ' . $source->batch_code . ($request->reason ? ' — ' . $request->reason : ''),
            'type'                => 'conversion_in',
            'adjustment_date'     => now()->format('Y-m-d'),
            'adjusted_by_id'      => Auth::id(),
        ]);

        return [
            'data'    => ['output_batch_code' => $newBatchCode, 'output_quantity' => $outputQty],
            'message' => 'Stock converted successfully.',
            'info'    => 'New batch ' . $newBatchCode . ' created with ' . $outputQty . ' units.',
            'status'  => true,
        ];
    }
}
