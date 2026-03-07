<?php

namespace App\Services\System\PurchaseOrder;

use App\Models\ListStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Http\Resources\System\PurchaseOrder\StockReturnResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReturnClass
{
    public function list($request)
    {
        $data = StockReturn::with([
            'purchaseOrder.status',
            'purchaseOrder.supplier',
            'items.purchaseOrderItem.product',
            'items.status',
            'status',
            'createdBy',
            'approvedBy',
        ])->orderByDesc('id')
            ->paginate($request->count ?? 10);

        return StockReturnResource::collection($data);
    }

    public function view($id)
    {
        $data = StockReturn::with([
            'purchaseOrder.status',
            'purchaseOrder.supplier',
            'items.purchaseOrderItem.product',
            'items.status',
            'status',
            'createdBy',
            'approvedBy',
        ])->findOrFail($id);

        return new StockReturnResource($data);
    }

    public function store($request)
    {
        $data = DB::transaction(function () use ($request) {
            $requestedItems = collect($request->items ?? [])
                ->filter(function ($item) {
                    return !empty($item['po_item_id']) && !empty($item['quantity']);
                })
                ->values();

            if ($requestedItems->isEmpty()) {
                $this->fail('Please select at least one item to return.');
            }

            $poItemIds = $requestedItems->pluck('po_item_id')->unique()->values();
            $poItems = PurchaseOrderItem::with('product')
                ->whereIn('id', $poItemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($poItems->count() !== $poItemIds->count()) {
                $this->fail('One or more selected items are invalid.');
            }

            $poId = $poItems->first()->po_id;
            $mixedPoItems = $poItems->contains(function ($item) use ($poId) {
                return (int) $item->po_id !== (int) $poId;
            });
            if ($mixedPoItems) {
                $this->fail('Selected return items must belong to a single purchase order.');
            }

            $purchaseOrder = PurchaseOrder::findOrFail($poId);

            $hasPendingStockReturn = StockReturn::where('po_id', $purchaseOrder->id)
                ->where('status_id', ListStatus::where('slug', 'pending')->first()?->id)
                ->exists();

            if ($hasPendingStockReturn) {
                $this->fail('This purchase order already has a pending stock return.');
            }

            $totalReturnQuantity = 0;

            foreach ($requestedItems as $requestItem) {
                $poItem = $poItems->get((int) $requestItem['po_item_id']);
                $returnQty = (int) $requestItem['quantity'];

                if (!$poItem || $returnQty < 1) {
                    $this->fail('Invalid return quantity.');
                }

                if ($returnQty > (int) $poItem->received_quantity) {
                    $productName = $poItem->product?->name ?? 'selected item';
                    $this->fail("Return quantity exceeds received quantity for {$productName}.");
                }

                $totalReturnQuantity += $returnQty;
            }

            if ($totalReturnQuantity < 1) {
                $this->fail('Total return quantity must be at least 1.');
            }

            $stockReturn = StockReturn::create([
                'po_id' => $purchaseOrder->id,
                'reason' => trim((string) ($request->reason ?? 'Itemized stock return')) ?: 'Itemized stock return',
                'status_id' => ListStatus::where('slug', 'pending')->first()?->id,
                'created_by_id' => Auth::id(),
                'approved_by_id' => null,
                'approved_at' => null,
            ]);

            foreach ($requestedItems as $requestItem) {
                $poItem = $poItems->get((int) $requestItem['po_item_id']);
                $returnQty = (int) $requestItem['quantity'];
                $remarks = isset($requestItem['remarks']) ? trim((string) $requestItem['remarks']) : null;

                StockReturnItem::create([
                    'stock_return_id' => $stockReturn->id,
                    'po_item_id' => $poItem->id,
                    'quantity' => $returnQty,
                    'returned_quantity' => 0,
                    'remarks' => $remarks,
                    'status_id' => ListStatus::where('slug', 'pending')->first()?->id,
                ]);
            }

            return $stockReturn->fresh([
                'purchaseOrder.status',
                'purchaseOrder.supplier',
                'items.purchaseOrderItem.product',
                'items.status',
                'status',
                'createdBy',
                'approvedBy',
            ]);
        });

        return [
            'data' => new StockReturnResource($data),
            'message' => 'Purchase order return processed successfully!',
            'info' => "You've successfully processed the purchase order return.",
            'status' => true,
        ];
    }

    protected function fail($message)
    {
        throw ValidationException::withMessages([
            'items' => [$message],
        ]);
    }
}
