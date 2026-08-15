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

        // Validate target product exists and is active
        $targetProduct = Product::find($request->product_id);
        if (!$targetProduct || !$targetProduct->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product is inactive or does not exist.'],
            ]);
        }

        $sourceWeight = floatval($source->receivedItem?->product?->weight ?? $source->product?->weight ?? 0);
        $targetWeight = floatval($targetProduct->weight ?? 0);

        $selectedLossIds     = $request->weight_loss_ids ?? [];
        $isShortWeightMode   = !empty($selectedLossIds) && $sourceWeight > 0 && $targetWeight > 0;
        $computedRemainderKg = 0.0;

        if ($isShortWeightMode) {
            // Short-weight mode: derive the output count, source sacks used, ratio
            // and remainder ENTIRELY from the selected weight-loss records, so a
            // hand-crafted conversion_ratio (e.g. a direct API call) cannot make
            // the output quantity and the returned partial sack disagree.
            $selectedLosses = InventoryWeightLoss::whereIn('id', $selectedLossIds)
                ->where('inventory_stock_id', $source->id)
                ->get();

            if ($selectedLosses->isEmpty()) {
                throw ValidationException::withMessages([
                    'weight_loss_ids' => ['The selected short-weight groups are invalid for this batch.'],
                ]);
            }

            $selectedTotalKg     = (float) $selectedLosses->sum(
                fn ($wl) => ($wl->affected_sacks ?? 0) * ($sourceWeight - ($wl->loss_per_sack ?? 0))
            );
            $sourceQtyUsed       = (int) $selectedLosses->sum(fn ($wl) => (int) ($wl->affected_sacks ?? 0));
            $outputQty           = (int) floor($selectedTotalKg / $targetWeight);
            $conversionRatio     = $sourceQtyUsed > 0 ? round($outputQty / $sourceQtyUsed, 6) : 0;
            $computedRemainderKg = fmod($selectedTotalKg, $targetWeight);
        } else {
            // Normal mode: convert a whole number of source sacks by the ratio.
            if (empty($request->conversion_ratio) || floatval($request->conversion_ratio) <= 0) {
                throw ValidationException::withMessages([
                    'conversion_ratio' => ['Conversion ratio must be greater than zero.'],
                ]);
            }
            $sourceQtyUsed   = (int) $request->source_qty_used;
            $conversionRatio = floatval($request->conversion_ratio);
            $outputQty       = (int) round($sourceQtyUsed * $conversionRatio);
        }

        if ($sourceQtyUsed < 1) {
            throw ValidationException::withMessages([
                'source_qty_used' => ['Please select at least one sack to convert.'],
            ]);
        }

        // Validate available quantity
        if ($source->quantity < $sourceQtyUsed) {
            throw ValidationException::withMessages([
                'source_qty_used' => ['Not enough stock. Available: ' . $source->quantity . ' units.'],
            ]);
        }

        if ($outputQty < 1) {
            throw ValidationException::withMessages([
                'conversion_ratio' => ['Conversion results in zero output units.'],
            ]);
        }

        $newBatchCode = $this->series->get('batch_code') . '-Con';

        // Use frontend-computed unit cost if provided, otherwise derive from source
        $sourceUnitCost = floatval($source->receivedItem?->unit_cost ?? $source->unit_cost ?? 0);
        $unitCostPerKg  = $sourceWeight > 0 ? $sourceUnitCost / $sourceWeight : 0;
        $derivedCost    = $unitCostPerKg * $targetWeight;
        $outputUnitCost = floatval($request->unit_cost ?? 0) > 0
            ? floatval($request->unit_cost)
            : $derivedCost;

        $conversion = ProductConversion::create([
            'source_stock_id'  => $source->id,
            'output_stock_id'  => null,
            'source_qty_used'  => $sourceQtyUsed,
            'conversion_ratio' => $conversionRatio,
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

        // Value of any leftover source material that gets returned to the
        // source batch below rather than consumed — must be re-debited to
        // inventory in the journal entry, or it reads as a real loss.
        $returnedValue = $computedRemainderKg > 0.001 ? round($computedRemainderKg * $unitCostPerKg, 2) : 0.0;

        $this->journalEntryService->recordStockConversionEntry($conversion->fresh([
            'sourceStock.receivedItem.product',
            'sourceStock.product',
            'outputStock.product',
        ]), $returnedValue);

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
        $source->decrement('quantity', $sourceQtyUsed);

        InventoryAdjustment::create([
            'inventory_stocks_id' => $source->id,
            'previous_quantity'   => $prevQty,
            'new_quantity'        => $source->quantity,
            'reason'              => 'Conversion out → ' . $newBatchCode . ($request->reason ? ' — ' . $request->reason : ''),
            'type'                => 'conversion_out',
            'adjustment_date'     => now()->format('Y-m-d'),
            'adjusted_by_id'      => Auth::id(),
        ]);

        // Return whatever didn't fit in the output units back to the source
        // batch, as full-weight sacks plus (if anything is left over) one
        // short-weight sack — not just a single partial sack. A remainder
        // can be larger than one source sack whenever the target unit is
        // heavier than the source (e.g. 5 x 10kg sacks -> a 25kg product
        // only consumes 1 unit's worth and leaves 22.5kg, more than two
        // whole source sacks); the old code only ever handled a remainder
        // smaller than one source sack, silently dropping the rest.
        if ($computedRemainderKg > 0.001 && $sourceWeight > 0) {
            $wholeSacksReturned = (int) floor($computedRemainderKg / $sourceWeight);
            $subSackRemainderKg = round($computedRemainderKg - ($wholeSacksReturned * $sourceWeight), 4);
            $totalSacksReturned = $wholeSacksReturned;

            if ($subSackRemainderKg > 0.001 && $sourceWeight > $subSackRemainderKg) {
                $lossPerSack = round($sourceWeight - $subSackRemainderKg, 4);
                $totalSacksReturned += 1;

                InventoryWeightLoss::create([
                    'inventory_stock_id' => $source->id,
                    'affected_sacks'     => 1,
                    'loss_per_sack'      => $lossPerSack,
                    'loss_kg'            => $lossPerSack,
                    'reason'             => 'Partial sack remaining from conversion — ' . round($subSackRemainderKg, 2) . ' kg',
                    'recorded_by_id'     => Auth::id(),
                    'recorded_at'        => now(),
                ]);
            }

            if ($totalSacksReturned > 0) {
                $prevAfterDecrement = $source->quantity;
                $source->increment('quantity', $totalSacksReturned);

                $reasonParts = [];
                if ($wholeSacksReturned > 0) {
                    $reasonParts[] = $wholeSacksReturned . ' full sack(s)';
                }
                if ($subSackRemainderKg > 0.001) {
                    $reasonParts[] = round($subSackRemainderKg, 2) . ' kg partial sack';
                }

                InventoryAdjustment::create([
                    'inventory_stocks_id' => $source->id,
                    'previous_quantity'   => $prevAfterDecrement,
                    'new_quantity'        => $source->quantity,
                    'reason'              => implode(' + ', $reasonParts) . ' returned to source after conversion ('
                        . round($computedRemainderKg, 2) . ' kg total)',
                    'type'                => 'conversion_partial',
                    'adjustment_date'     => now()->format('Y-m-d'),
                    'adjusted_by_id'      => Auth::id(),
                ]);
            }
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
