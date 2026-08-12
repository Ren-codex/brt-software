<?php

namespace App\Services\Modules;

use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\Product;
use App\Services\NotificationService;

class InventoryService
{
    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Base query for a product's inventory stock rows. Matches stock received
     * through a purchase (linked via receivedItem) AND stock created via
     * Product Conversion (no receivedItem — linked via conversion_id
     * instead), mirroring DropdownClass::products(). Using only the
     * receivedItem relation here would make converted-batch stock invisible
     * to stock checks/deductions even though it's real, sellable inventory.
     *
     * @param  int  $productId
     * @param  string|null  $batchCode
     */
    private function stockQuery($productId, $batchCode = null)
    {
        $query = InventoryStocks::where(function ($q) use ($productId) {
            $q->whereHas('receivedItem', function ($sub) use ($productId) {
                $sub->where('product_id', $productId);
            })->orWhere(function ($sub) use ($productId) {
                $sub->where('product_id', $productId)
                    ->whereNotNull('conversion_id');
            });
        });

        if ($batchCode) {
            $query->where('batch_code', $batchCode);
        }

        return $query;
    }

    /**
     * Get the current stock quantity for a product.
     *
     * @param  int  $productId
     * @param  string|null  $batchCode
     * @return int
     */
    public function getCurrentStock($productId, $batchCode = null)
    {
        return $this->stockQuery($productId, $batchCode)->sum('quantity');
    }

    /**
     * Check if there is sufficient stock for a product.
     *
     * @param  int  $productId
     * @param  int  $quantity
     * @param  string|null  $batchCode
     * @return bool
     */
    public function hasSufficientStock($productId, $quantity, $batchCode = null)
    {
        return $this->getCurrentStock($productId, $batchCode) >= $quantity;
    }

    /**
     * Deduct stock from inventory using FIFO method.
     *
     * @param  int  $productId
     * @param  int  $quantity
     * @param  string  $reason
     * @param  string|null  $batchCode
     *
     * @throws \Exception
     */
    public function deductStock($productId, $quantity, $reason, $batchCode = null)
    {
        $previousTotal = (int) $this->getCurrentStock($productId);
        $remainingQuantity = $quantity;

        $inventoryStocks = $this->stockQuery($productId, $batchCode)->orderBy('created_at')->get();

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
                'type' => 'deduction', // Subtraction
            ]);

            $remainingQuantity -= $deductAmount;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception('Insufficient stock to deduct the requested quantity.');
        }

        $this->notificationService->checkAndNotifyLowStock($productId, $previousTotal);
    }

    /**
     * Add stock to inventory using LIFO method (add to the most recent stock).
     *
     * @param  int  $productId
     * @param  int  $quantity
     * @param  string  $reason
     * @param  string|null  $batchCode
     */
    public function addStock($productId, $quantity, $reason, $batchCode = null)
    {
        // Get the most recent inventory stock for the product (LIFO), optionally filtered by batch_code
        $stock = $this->stockQuery($productId, $batchCode)->orderBy('created_at', 'desc')->first();

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
                'type' => 'addition', // Addition
            ]);
        } else {
            // No stock row exists for this product/batch. Rather than silently
            // dropping the quantity (which would lose real, returned inventory),
            // fail loudly so the surrounding transaction rolls back and the
            // problem surfaces instead of vanishing.
            throw new \Exception('Cannot restore stock: no inventory batch found for product #' . $productId . ($batchCode ? ' (batch ' . $batchCode . ')' : '') . '.');
        }
    }

    /**
     * Record and apply a loss/damage stock deduction.
     *
     * @param  int  $productId
     * @param  int  $quantity
     * @param  string  $reason
     * @param  string|null  $batchCode
     * @param  string  $type
     *
     * @throws \Exception
     */
    public function recordLossOrDamage($productId, $quantity, $reason, $batchCode = null, $type = 'loss')
    {
        $previousTotal = (int) $this->getCurrentStock($productId);
        $remainingQuantity = (int) $quantity;

        $inventoryStocks = $this->stockQuery($productId, $batchCode)->orderBy('created_at')->get();

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

        $this->notificationService->checkAndNotifyLowStock($productId, $previousTotal);
    }
}
