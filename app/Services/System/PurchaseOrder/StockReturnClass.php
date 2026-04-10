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
use App\Services\SeriesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReturnClass
{
    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) {
        $this->series_service = $series_service;
    }

    public function list($request)
    {
        $data = StockReturn::with([
            'purchaseOrder.status',
            'purchaseOrder.supplier',
            'items.purchaseOrderItem.product',
            'items.status',
            'items.receivedBy',
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
            'items.receivedBy',
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

                $inventoryStocks = InventoryStocks::whereHas('receivedItem', function ($query) use ($poItem) {
                    $query->where('po_item_id', $poItem->id);
                })
                    ->where('quantity', '>', 0)
                    ->lockForUpdate()
                    ->get();

                $totalInventoryQty = (int) $inventoryStocks->sum('quantity');
                $productName = $poItem->product?->name ?? 'selected item';

                if ($totalInventoryQty <= 0) {
                    $this->fail("{$productName} cannot be returned because inventory stock quantity is 0.");
                }

                if ($returnQty > $totalInventoryQty) {
                    $this->fail("Return quantity exceeds available inventory stock for {$productName}.");
                }

                $totalReturnQuantity += $returnQty;
            }

            if ($totalReturnQuantity < 1) {
                $this->fail('Total return quantity must be at least 1.');
            }

            $stockReturnNo = null;
            try {
                $stockReturnNo = $this->series_service->get('stock_return');
            } catch (\Throwable $e) {
                $stockReturnNo = null;
            }

            $stockReturn = StockReturn::create([
                'stock_return_no' => $stockReturnNo,
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
                'items.receivedBy',
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
                $item->status_id = $targetStatusId;
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
                    'action' => 'Stock Return Approved',
                    'remarks' => "Stock return #{$stockReturn->id} approved. Awaiting delivery to supplier.",
                ]);
            }

            return $stockReturn->fresh([
                'purchaseOrder.status',
                'purchaseOrder.supplier',
                'items.purchaseOrderItem.product',
                'items.status',
                'items.receivedBy',
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

    public function receiveItem($request, $stockReturnId, $itemId)
    {
        $data = DB::transaction(function () use ($request, $stockReturnId, $itemId) {
            $stockReturn = StockReturn::with(['status', 'purchaseOrder.supplier'])
                ->lockForUpdate()
                ->findOrFail($stockReturnId);

            $deliveredStatusId = $this->getStatusIdBySlug('delivered');
            if (!$deliveredStatusId) {
                $this->fail('Delivered status is not configured.');
            }

            if ((int) $stockReturn->status_id !== (int) $deliveredStatusId) {
                $this->fail('Only delivered stock returns can receive returned items.');
            }

            $hasDeliveryLog = StockReturnLog::where('stock_return_id', $stockReturn->id)
                ->where('action', 'Supplier Delivery Logged')
                ->exists();
            if (!$hasDeliveryLog) {
                $this->fail('Supplier delivery must be logged before receiving replacement items.');
            }

            $stockReturnItem = StockReturnItem::with(['purchaseOrderItem.product', 'status'])
                ->where('stock_return_id', $stockReturn->id)
                ->where('id', $itemId)
                ->lockForUpdate()
                ->firstOrFail();

            $replacedQty = (int) $request->replaced_quantity;
            $lossQty = (int) $request->loss_quantity;
            $actualReceivedQty = $replacedQty + $lossQty;
            $requestedQty = (int) $stockReturnItem->quantity;
            $currentReturnedQty = (int) $stockReturnItem->returned_quantity;
            $currentReplacedQty = (int) $stockReturnItem->replaced_quantity;
            $currentLossQty = (int) $stockReturnItem->loss_quantity;
            $remainingQty = max($requestedQty - $currentReturnedQty, 0);

            if ($actualReceivedQty < 1) {
                $this->fail('Replacement plus loss quantity must be at least 1.');
            }

            if ($actualReceivedQty > $remainingQty) {
                $this->fail("Replacement plus loss quantity cannot be greater than the remaining returned quantity of {$remainingQty}.");
            }

            $remarks = trim((string) ($request->remarks ?? ''));
            $updatedReturnedQty = $currentReturnedQty + $actualReceivedQty;
            $updatedReplacedQty = $currentReplacedQty + $replacedQty;
            $updatedLossQty = $currentLossQty + $lossQty;

            $stockReturnItem->returned_quantity = $updatedReturnedQty;
            $stockReturnItem->replaced_quantity = $updatedReplacedQty;
            $stockReturnItem->loss_quantity = $updatedLossQty;
            $pendingStatusId = $this->getStatusIdBySlug('pending');
            $replacedStatusId = $this->getStatusIdBySlug('replaced');
            $lossStatusId = $this->getStatusIdBySlug('loss');
            if (!$pendingStatusId || !$replacedStatusId || !$lossStatusId) {
                $this->fail('Received statuses are not configured.');
            }

            $isFullyResolved = $updatedReturnedQty === $requestedQty
                && ($updatedReplacedQty + $updatedLossQty) === $requestedQty;

            if (!$isFullyResolved) {
                $stockReturnItem->status_id = $pendingStatusId;
            } elseif ($updatedReplacedQty > 0) {
                $stockReturnItem->status_id = $replacedStatusId;
            } else {
                $stockReturnItem->status_id = $lossStatusId;
            }
            $stockReturnItem->remarks = $remarks !== '' ? $remarks : null;
            $stockReturnItem->received_by_id = Auth::id();
            $stockReturnItem->received_at = now();

            if ($replacedQty > 0) {
                $poItem = PurchaseOrderItem::lockForUpdate()->find($stockReturnItem->po_item_id);
                if (!$poItem) {
                    $this->fail('One or more purchase order items are invalid.');
                }

                $poItem->received_quantity = (int) $poItem->received_quantity + $replacedQty;
                if ((int) $poItem->received_quantity >= (int) $poItem->quantity) {
                    $poItem->status = 'received';
                }
                $poItem->save();

                $inventoryStock = InventoryStocks::whereHas('receivedItem', function ($query) use ($poItem) {
                    $query->where('po_item_id', $poItem->id);
                })
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (!$inventoryStock) {
                    $this->fail('No inventory stock record found for this purchase order item.');
                }

                $inventoryStock->quantity = (int) $inventoryStock->quantity + $replacedQty;
                $inventoryStock->save();

                PurchaseOrderLog::create([
                    'po_id' => $stockReturn->po_id,
                    'user_id' => Auth::id(),
                    'action' => 'Replacement Received',
                    'remarks' => "Returned stocks received for replacement (Stock Return #{$stockReturn->id}, Item #{$stockReturnItem->id}) qty: {$replacedQty}.",
                ]);
            }

            $stockReturnItem->save();

            $productName = $stockReturnItem->purchaseOrderItem?->product?->name ?? "Item #{$stockReturnItem->id}";
            $supplierName = $stockReturn->purchaseOrder?->supplier?->name ?? 'Unknown supplier';
            $receiverName = Auth::user()?->fullname ?? Auth::user()?->username ?? 'System';
            StockReturnLog::create([
                'stock_return_id' => $stockReturn->id,
                'user_id' => Auth::id(),
                'action' => 'Item Received',
                'remarks' => "Received replacement item from supplier: replaced {$replacedQty}, loss {$lossQty}, total {$actualReceivedQty}."
                    . ($remarks !== '' ? " {$remarks}" : ''),
            ]);

            $hasUnbalancedItems = StockReturnItem::where('stock_return_id', $stockReturn->id)
                ->whereRaw('(COALESCE(replaced_quantity, 0) + COALESCE(loss_quantity, 0)) < quantity')
                ->exists();

            if (!$hasUnbalancedItems) {
                $completedStatusId = $this->getStatusIdBySlug('completed');
                if (!$completedStatusId) {
                    $this->fail('Completed status is not configured.');
                }

                $stockReturn->status_id = $completedStatusId;
                $stockReturn->save();

                StockReturnLog::create([
                    'stock_return_id' => $stockReturn->id,
                    'user_id' => Auth::id(),
                    'action' => 'Completed',
                    'remarks' => 'Stock return completed. All returned quantities are resolved.',
                ]);
            }

            return $stockReturn->fresh([
                'purchaseOrder.status',
                'purchaseOrder.supplier',
                'items.purchaseOrderItem.product',
                'items.status',
                'items.receivedBy',
                'logs.user',
                'status',
                'createdBy',
                'approvedBy',
            ]);
        });

        return [
            'data' => new StockReturnResource($data),
            'message' => 'Return item received successfully!',
            'info' => "You've successfully recorded the received return item.",
            'status' => true,
        ];
    }

    public function logSupplierDelivery($stockReturnId)
    {
        $data = DB::transaction(function () use ($stockReturnId) {
            $stockReturn = StockReturn::with(['status', 'purchaseOrder.supplier', 'items.purchaseOrderItem.product'])
                ->lockForUpdate()
                ->findOrFail($stockReturnId);

            $approvedStatusId = $this->getStatusIdBySlug('approved');
            $deliveredStatusId = $this->getStatusIdBySlug('delivered');
            if (!$approvedStatusId || !$deliveredStatusId) {
                $this->fail('Approved or delivered status is not configured.');
            }

            if ((int) $stockReturn->status_id !== (int) $approvedStatusId) {
                $this->fail('Only approved stock returns can be marked as delivered to supplier.');
            }

            $hasDeliveryLog = StockReturnLog::where('stock_return_id', $stockReturn->id)
                ->where('action', 'Supplier Delivery Logged')
                ->exists();
            if ($hasDeliveryLog) {
                $this->fail('Supplier delivery has already been logged for this stock return.');
            }

            foreach ($stockReturn->items as $item) {
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

                $poItem->received_quantity = max(0, (int) $poItem->received_quantity - $returnQty);
                $poItem->status = 'pending';
                $poItem->save();
            }

            $supplierName = $stockReturn->purchaseOrder?->supplier?->name ?? 'Unknown supplier';

            StockReturnLog::create([
                'stock_return_id' => $stockReturn->id,
                'user_id' => Auth::id(),
                'action' => 'Supplier Delivery Logged',
                'remarks' => "Goods successfully returned back to supplier.",
            ]);

            PurchaseOrderLog::create([
                'po_id' => $stockReturn->po_id,
                'user_id' => Auth::id(),
                'action' => 'Delivered to Supplier',
                'remarks' => "Stock return #{$stockReturn->id} delivered to supplier {$supplierName}. Purchase order items returned to receiving.",
            ]);

            $stockReturn->status_id = $deliveredStatusId;
            $stockReturn->save();

            return $stockReturn->fresh([
                'purchaseOrder.status',
                'purchaseOrder.supplier',
                'items.purchaseOrderItem.product',
                'items.status',
                'items.receivedBy',
                'logs.user',
                'status',
                'createdBy',
                'approvedBy',
            ]);
        });

        return [
            'data' => new StockReturnResource($data),
            'message' => 'Supplier delivery logged successfully!',
            'info' => "You've successfully logged the supplier delivery.",
            'status' => true,
        ];
    }

    protected function getStatusIdBySlug($slug)
    {
        return ListStatus::where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [strtolower((string) $slug)])
            ->first()?->id;
    }

    protected function fail($message)
    {
        throw ValidationException::withMessages([
            'items' => [$message],
        ]);
    }
}
