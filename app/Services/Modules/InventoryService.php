<?php

namespace App\Services\Modules;

use App\Models\InventoryStocks;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Get the current stock quantity for a product.
     *
     * @param int $productId
     * @param string|null $batchCode
     * @return int
     */
    public function getCurrentStock($productId, $batchCode = null)
    {
        $query = InventoryStocks::whereHas('receivedItem', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        });

        if ($batchCode) {
            $query->where('batch_code', $batchCode);
        }

        return $query->sum('quantity');
    }

    /**
     * Check if there is sufficient stock for a product.
     *
     * @param int $productId
     * @param int $quantity
     * @param string|null $batchCode
     * @return bool
     */
    public function hasSufficientStock($productId, $quantity, $batchCode = null)
    {
        return $this->getCurrentStock($productId, $batchCode) >= $quantity;
    }

    /**
     * Deduct stock from inventory using FIFO method.
     *
     * @param int $productId
     * @param int $quantity
     * @param string $reason
     * @param string|null $batchCode
     * @throws \Exception
     */
    public function deductStock($productId, $quantity, $reason, $batchCode = null)
    {
        $remainingQuantity = $quantity;

        // Get inventory stocks for the product, optionally filtered by batch_code
        $query = InventoryStocks::whereHas('receivedItem', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        });

        if ($batchCode) {
            $query->where('batch_code', $batchCode);
        }

        $inventoryStocks = $query->orderBy('created_at')->get();

        foreach ($inventoryStocks as $stock) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $deductAmount = min($stock->quantity, $remainingQuantity);

            $previousQuantity = $stock->quantity;
            $newQuantity = $previousQuantity - $deductAmount;

            // Update the stock quantity
            $stock->update(['quantity' => $newQuantity]);

            // Create an inventory adjustment record
            InventoryAdjustment::create([
                'inventory_stocks_id' => $stock->id,
                'new_quantity' => $newQuantity,
                'previous_quantity' => $previousQuantity,
                'reason' => $reason,
                'adjustment_date' => now()->toDateString(),
                'adjusted_by_id' => auth()->id(),
                'type' => 2, // Subtraction
            ]);

            $remainingQuantity -= $deductAmount;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception('Insufficient stock to deduct the requested quantity.');
        }
    }

    /**
     * Add stock to inventory using LIFO method (add to the most recent stock).
     *
     * @param int $productId
     * @param int $quantity
     * @param string $reason
     * @param string|null $batchCode
     */
    public function addStock($productId, $quantity, $reason, $batchCode = null)
    {
        // Get the most recent inventory stock for the product (LIFO), optionally filtered by batch_code
        $query = InventoryStocks::whereHas('receivedItem', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        });

        if ($batchCode) {
            $query->where('batch_code', $batchCode);
        }

        $stock = $query->orderBy('created_at', 'desc')->first();

        if ($stock) {
            $previousQuantity = $stock->quantity;
            $newQuantity = $previousQuantity + $quantity;

            // Update the stock quantity
            $stock->update(['quantity' => $newQuantity]);

            // Create an inventory adjustment record
            InventoryAdjustment::create([
                'inventory_stocks_id' => $stock->id,
                'new_quantity' => $newQuantity,
                'previous_quantity' => $previousQuantity,
                'reason' => $reason,
                'adjustment_date' => now()->toDateString(),
                'adjusted_by_id' => auth()->id(),
                'type' => 1, // Addition
            ]);
        } else {
            // If no stock exists, this might be an error, but for now, we'll skip
            // In a real scenario, you might need to create a new stock entry
        }
    }

    /**
     * Record and apply a loss/damage stock deduction.
     *
     * @param int $productId
     * @param int $quantity
     * @param string $reason
     * @param string|null $batchCode
     * @param string $type
     * @throws \Exception
     */
    public function recordLossOrDamage($productId, $quantity, $reason, $batchCode = null, $type = 'loss')
    {
        $remainingQuantity = (int) $quantity;

        $query = InventoryStocks::whereHas('receivedItem', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        });

        if ($batchCode) {
            $query->where('batch_code', $batchCode);
        }

        $inventoryStocks = $query->orderBy('created_at')->get();

        foreach ($inventoryStocks as $stock) {
            if ($remainingQuantity <= 0) {
                break;
            }

            if ((int) $stock->quantity <= 0) {
                continue;
            }

            $deductAmount = min((int) $stock->quantity, $remainingQuantity);
            $previousQuantity = (int) $stock->quantity;
            $newQuantity = $previousQuantity - $deductAmount;

            $stock->update(['quantity' => $newQuantity]);

            InventoryAdjustment::create([
                'inventory_stocks_id' => $stock->id,
                'new_quantity' => $newQuantity,
                'previous_quantity' => $previousQuantity,
                'reason' => $reason,
                'adjustment_date' => now()->toDateString(),
                'adjusted_by_id' => auth()->id(),
                'type' => $type, // e.g. "loss" or "damage"
            ]);

            $remainingQuantity -= $deductAmount;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception('Insufficient stock to classify requested quantity as loss/damaged.');
        }
    }
}
