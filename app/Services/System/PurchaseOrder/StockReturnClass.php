<?php

namespace App\Services\System\PurchaseOrder;

use App\Models\InventoryStocks;
use App\Models\ListStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderLog;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Models\StockReturnLog;
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
            'logs.user',
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
            'logs.user',
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

            StockReturnLog::create([
                'stock_return_id' => $stockReturn->id,
                'user_id' => Auth::id(),
                'action' => 'Created',
                'remarks' => 'Stock return created.',
            ]);

            return $stockReturn->fresh([
                'purchaseOrder.status',
                'purchaseOrder.supplier',
                'items.purchaseOrderItem.product',
                'items.status',
                'logs.user',
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

    public function approve($request, $id)
    {
        $data = DB::transaction(function () use ($request, $id) {
            $stockReturn = StockReturn::with(['items.purchaseOrderItem', 'status'])
                ->lockForUpdate()
                ->findOrFail($id);

            $pendingStatusId = ListStatus::where('slug', 'pending')->first()?->id;
            $approvedStatusId = ListStatus::where('slug', 'approved')->first()?->id;
            $disapprovedStatusId = ListStatus::where('slug', 'disapproved')->first()?->id;
            $targetStatus = strtolower((string) $request->status);

            if (!$approvedStatusId || !$disapprovedStatusId) {
                $this->fail('Approved status is not configured.');
            }

            if ((int) $stockReturn->status_id !== (int) $pendingStatusId) {
                $this->fail('Only pending stock returns can be approved.');
            }

            $targetStatusId = $targetStatus === 'approved' ? $approvedStatusId : $disapprovedStatusId;
            $remarks = trim((string) ($request->remarks ?? ''));

            foreach ($stockReturn->items as $item) {
                if ($targetStatus === 'approved') {
                    $poItem = PurchaseOrderItem::lockForUpdate()->find($item->po_item_id);
                    if (!$poItem) {
                        $this->fail('One or more purchase order items are invalid.');
                    }

                    $returnQty = (int) $item->quantity;
                    if ($returnQty < 1) {
                        $this->fail('Invalid return quantity found.');
                    }

                    if ($returnQty > (int) $poItem->received_quantity) {
                        $productName = $poItem->product?->name ?? 'selected item';
                        $this->fail("Return quantity exceeds received quantity for {$productName}.");
                    }

                    $inventoryStocks = InventoryStocks::whereHas('receivedItem', function ($query) use ($poItem) {
                        $query->where('po_item_id', $poItem->id);
                    })
                        ->where('quantity', '>', 0)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $totalInventoryQty = (int) $inventoryStocks->sum('quantity');
                    if ($returnQty > $totalInventoryQty) {
                        $productName = $poItem->product?->name ?? 'selected item';
                        $this->fail("Return quantity exceeds available inventory stock for {$productName}.");
                    }

                    $remainingQty = $returnQty;
                    foreach ($inventoryStocks as $inventoryStock) {
                        if ($remainingQty <= 0) {
                            break;
                        }

                        $deductQty = min((int) $inventoryStock->quantity, $remainingQty);
                        if ($deductQty > 0) {
                            $inventoryStock->decrement('quantity', $deductQty);
                            $remainingQty -= $deductQty;
                        }
                    }

                    $poItem->decrement('received_quantity', $returnQty);
                }

                $item->status_id = $targetStatus === 'approved' ? $pendingStatusId : $targetStatusId;
                $item->save();
            }

            $stockReturn->status_id = $targetStatusId;
            $stockReturn->approved_by_id = Auth::id();
            $stockReturn->approved_at = now();
            $stockReturn->save();

            StockReturnLog::create([
                'stock_return_id' => $stockReturn->id,
                'user_id' => Auth::id(),
                'action' => $targetStatus === 'approved' ? 'Approved' : 'Disapproved',
                'remarks' => $remarks !== ''
                    ? $remarks
                    : ($targetStatus === 'approved' ? 'Stock return approved.' : 'Stock return disapproved.'),
            ]);

            if ($targetStatus === 'approved') {
                PurchaseOrderLog::create([
                    'po_id' => $stockReturn->po_id,
                    'user_id' => Auth::id(),
                    'action' => 'Returned Stocks',
                    'remarks' => "Stock return #{$stockReturn->id} approved and stocks returned.",
                ]);
            }

            return $stockReturn->fresh([
                'purchaseOrder.status',
                'purchaseOrder.supplier',
                'items.purchaseOrderItem.product',
                'items.status',
                'logs.user',
                'status',
                'createdBy',
                'approvedBy',
            ]);
        });

        return [
            'data' => new StockReturnResource($data),
            'message' => $request->status === 'approved'
                ? 'Stock return approved successfully!'
                : 'Stock return disapproved successfully!',
            'info' => $request->status === 'approved'
                ? "You've successfully approved the stock return."
                : "You've successfully disapproved the stock return.",
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
