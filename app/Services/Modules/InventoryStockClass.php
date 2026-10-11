<?php

namespace App\Services\Modules;

use App\Http\Resources\InventoryStockResource;
use App\Http\Resources\ReceivedStockResource;
use App\Models\InventoryStocks;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryAdjustment;
use Illuminate\Support\Facades\Auth;

class InventoryStockClass
{
    public function lists($request)
    {
        $data = InventoryStockResource::collection(
            InventoryStocks::with('receivedItem.product', 'product', 'inventoryAdjustments', 'weightLosses')
                ->when($request->keyword, function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->whereHas('receivedItem.product', function ($q2) use ($keyword) {
                            $q2->where('name', 'LIKE', "%{$keyword}%");
                        })->orWhereHas('product', function ($q2) use ($keyword) {
                            $q2->where('name', 'LIKE', "%{$keyword}%");
                        })->orWhere('batch_code', 'LIKE', "%{$keyword}%");
                    });
                })
                ->when($request->exclude_converted, fn ($q) => $q->whereNull('conversion_id'))
                ->orderBy('created_at', 'DESC')
                ->sortFromRequest()
                ->paginate($request->count)
        );
        return $data;
    }

    public function view($id)
    {
        $inventoryStock = InventoryStocks::with([
            'inventoryAdjustments.adjustedBy.employee',
            'receivedItem.product',
            'product',
            'conversion.sourceStock.receivedItem.product',
            'conversion.convertedBy.employee',
            'conversionsOut.outputStock.receivedItem.product',
            'conversionsOut.outputStock.product',
            'conversionsOut.convertedBy.employee',
            'weightLosses.recordedBy.employee',
            'weightLosses.convertedBy.employee',
        ])->findOrFail($id);
        return new InventoryStockResource($inventoryStock);
    }

    public function update($request)
    {
        try {
            DB::beginTransaction();

            $inventoryStock = InventoryStocks::findOrFail($request->inventory_stocks_id);

            $is_retail_price = $request->retail_price != $inventoryStock->retail_price ? true : false;
            $is_wholesale_price = $request->wholesale_price != $inventoryStock->wholesale_price ? true : false;

            if($is_retail_price == true){
                InventoryAdjustment::create([
                    'inventory_stocks_id' =>  $request->inventory_stocks_id,
                    'new_quantity' =>  $request->retail_price,
                    'previous_quantity' =>  $inventoryStock->retail_price ?? 0,
                    'reason' =>  $request->reason,
                    'type' =>  'update retail price',
                    'adjustment_date' =>  now(),
                    'adjusted_by_id' =>  Auth::id(),
                ]);
            }

            if($is_wholesale_price == true){
                InventoryAdjustment::create([
                    'inventory_stocks_id' =>  $request->inventory_stocks_id,
                    'new_quantity' =>  $request->wholesale_price,
                    'previous_quantity' =>  $inventoryStock->wholesale_price ?? 0,
                    'reason' =>  $request->reason,
                    'type' =>  'update wholesale price',
                    'adjustment_date' =>  now(),
                    'adjusted_by_id' =>  Auth::id(),
                ]);
            }
            
            $inventoryStock->update([
                'retail_price' => $request->retail_price,
                'wholesale_price' => $request->wholesale_price,
            ]);


            DB::commit();

            return new InventoryStockResource($inventoryStock);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateSettings($request, $id)
    {
        $stock = InventoryStocks::findOrFail($id);

        $changes = [];

        if ($request->batch_code && $request->batch_code !== $stock->batch_code) {
            $changes[] = 'Batch code changed from ' . $stock->batch_code . ' to ' . $request->batch_code;
        }
        if ($request->expiration_date !== $stock->expiration_date) {
            $changes[] = 'Expiration date updated';
        }
        if ($request->unit_cost != $stock->unit_cost) {
            $changes[] = 'Unit cost updated from ' . $stock->unit_cost . ' to ' . $request->unit_cost;
        }
        if ((bool) $request->is_archived !== (bool) $stock->is_archived) {
            $changes[] = $request->is_archived ? 'Batch archived' : 'Batch unarchived';
        }
        if ((bool) $request->is_expired !== (bool) $stock->is_expired) {
            $changes[] = $request->is_expired ? 'Marked as expired' : 'Marked as not expired';
        }

        $stock->update([
            'batch_code'      => $request->batch_code      ?? $stock->batch_code,
            'expiration_date' => $request->expiration_date ?? null,
            'unit_cost'       => $request->unit_cost       ?? $stock->unit_cost,
            'notes'           => $request->notes           ?? null,
            'is_archived'     => (bool) ($request->is_archived ?? false),
            'is_expired'      => (bool) ($request->is_expired  ?? false),
        ]);

        if (!empty($changes)) {
            InventoryAdjustment::create([
                'inventory_stocks_id' => $stock->id,
                'previous_quantity'   => $stock->quantity,
                'new_quantity'        => $stock->quantity,
                'reason'              => implode('; ', $changes),
                'type'                => 'settings_update',
                'adjustment_date'     => now()->format('Y-m-d'),
                'adjusted_by_id'      => Auth::id(),
            ]);
        }

        return [
            'data'    => new InventoryStockResource($stock),
            'message' => 'Stock settings updated successfully.',
            'info'    => empty($changes) ? 'No changes detected.' : implode('; ', $changes),
            'status'  => true,
        ];
    }
    /**
     * Stock on hand with every batch of a product added into one line.
     *
     * A product is usually sitting in several batches at once — each delivery
     * is its own, with its own cost and price — so "how much of this do we
     * have" means adding them up. This does that, and keeps the batches
     * underneath so the total can be checked against them.
     *
     * Archived batches and empty ones are left out: neither is stock anyone
     * can sell. A batch knows its product either directly (a converted batch)
     * or through the delivery line it came from, so both are read.
     */
    public function consolidation(): array
    {
        $stocks = InventoryStocks::with([
            'product.brand', 'product.unit',
            'receivedItem.product.brand', 'receivedItem.product.unit',
            'receivedItem.receivedStock:id,received_date',
        ])
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q->where('is_archived', false)->orWhereNull('is_archived'))
            ->get();

        $rows = $stocks
            ->groupBy(fn ($stock) => $stock->product_id ?: optional($stock->receivedItem)->product_id)
            ->filter(fn ($batches, $productId) => (int) $productId > 0)
            ->map(function ($batches, $productId) {
                $product = $batches->first()->product ?: optional($batches->first()->receivedItem)->product;
                $name = $product
                    ? trim(collect([optional($product->brand)->name, $product->weight, optional($product->unit)->name])->filter()->implode(' '))
                    : 'Product #'.$productId;

                // Received stock carries its cost on the delivery line; only a
                // converted batch has one of its own. The Inventory Report reads
                // it the same way, so the two always agree.
                $cost = fn ($b) => (float) $b->unit_cost > 0
                    ? (float) $b->unit_cost
                    : (float) optional($b->receivedItem)->unit_cost;

                $quantity = (float) $batches->sum('quantity');
                $stockValue = (float) $batches->sum(fn ($b) => (float) $b->quantity * $cost($b));
                $retailValue = (float) $batches->sum(fn ($b) => (float) $b->quantity * (float) $b->retail_price);
                $received = fn ($b) => optional(optional(optional($b->receivedItem)->receivedStock)->received_date)->format('Y-m-d');
                $minimum = (float) ($product->minimum_stock ?? 0);

                return [
                    'product_id' => (int) $productId,
                    'product_name' => $name ?: 'Product #'.$productId,
                    'batch_count' => $batches->count(),
                    'quantity' => $quantity,
                    'weight' => round($quantity * (float) ($product->weight ?? 0), 2),
                    // Weighted by quantity: ten sacks at 1,000 and one at 2,000
                    // cost 1,090.91 each on average, not 1,500.
                    'average_cost' => $quantity > 0 ? round($stockValue / $quantity, 2) : 0.0,
                    'stock_value' => round($stockValue, 2),
                    'retail_value' => round($retailValue, 2),
                    'minimum_stock' => $minimum,
                    'is_low' => $minimum > 0 && $quantity <= $minimum,
                    'oldest_received' => $batches->map($received)->filter()->min(),
                    'batches' => $batches
                        ->sortBy(fn ($b) => ($received($b) ?? '9999').'|'.$b->id)
                        ->map(fn ($b) => [
                            'id' => $b->id,
                            'batch_code' => $b->batch_code,
                            'received_date' => $received($b),
                            'expiration_date' => $b->expiration_date ? \Carbon\Carbon::parse($b->expiration_date)->format('Y-m-d') : null,
                            'quantity' => (float) $b->quantity,
                            'unit_cost' => $cost($b),
                            'retail_price' => (float) $b->retail_price,
                            'wholesale_price' => (float) $b->wholesale_price,
                            'stock_value' => round((float) $b->quantity * $cost($b), 2),
                        ])
                        ->values(),
                ];
            })
            ->sortBy('product_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'data' => $rows,
            'totals' => [
                'products' => $rows->count(),
                'batches' => (int) $rows->sum('batch_count'),
                'quantity' => (float) $rows->sum('quantity'),
                'weight' => round((float) $rows->sum('weight'), 2),
                'stock_value' => round((float) $rows->sum('stock_value'), 2),
                'retail_value' => round((float) $rows->sum('retail_value'), 2),
            ],
        ];
    }
}
