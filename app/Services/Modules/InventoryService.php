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
     * @return int
     */
    public function getCurrentStock($productId)
    {
        return InventoryStocks::whereHas('receivedItem', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        })->sum('quantity');
    }

    /**
     * Check if there is sufficient stock for a product.
     *
     * @param int $productId
     * @param int $quantity
     * @return bool
     */
    public function hasSufficientStock($productId, $quantity)
    {
        return $this->getCurrentStock($productId) >= $quantity;
    }

    /**
     * Deduct stock from inventory using FIFO method.
     *
     * @param int $productId
     * @param int $quantity
     * @param string $reason
     * @throws \Exception
     */
    public function deductStock($productId, $quantity, $reason)
    {
        $remainingQuantity = $quantity;

        // Get inventory stocks for the product, ordered by creation date (FIFO)
        $inventoryStocks = InventoryStocks::whereHas('receivedItem', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        })->orderBy('created_at')->get();

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
     */
    public function addStock($productId, $quantity, $reason)
    {
        // Get the most recent inventory stock for the product (LIFO)
        $stock = InventoryStocks::whereHas('receivedItem', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        })->orderBy('created_at', 'desc')->first();

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
}
