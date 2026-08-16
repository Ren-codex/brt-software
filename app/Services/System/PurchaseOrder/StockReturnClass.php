<?php

namespace App\Services\System\PurchaseOrder;

use App\Http\Resources\System\PurchaseOrder\StockReturnResource;
use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\ListStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderLog;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Models\StockReturnLog;
use App\Services\Accounting\JournalEntryService;
use App\Services\Modules\InventoryService;
use App\Services\NotificationService;
use App\Services\SeriesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReturnClass
{
    protected $series_service;

    protected $notificationService;

    protected $inventoryService;

    protected $journalEntryService;

    public function __construct(
        SeriesService $series_service,
        NotificationService $notificationService,
        InventoryService $inventoryService,
        JournalEntryService $journalEntryService,
    ) {
        $this->series_service = $series_service;
        $this->notificationService = $notificationService;
        $this->inventoryService = $inventoryService;
        $this->journalEntryService = $journalEntryService;
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
            'voidedBy',
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
            'voidedBy',
        ])->findOrFail($id);

        return new StockReturnResource($data);
    }

    public function store($request)
    {
        $data = DB::transaction(function () use ($request) {
            $requestedItems = collect($request->items ?? [])
                ->filter(function ($item) {
                    return ! empty($item['po_item_id']) && ! empty($item['quantity']);
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

                if (! $poItem || $returnQty < 1) {
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
                'voidedBy',
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

            if (! $approvedStatusId || ! $disapprovedStatusId) {
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
                    if (! $poItem) {
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

                    $previousTotal = (int) $this->inventoryService->getCurrentStock($poItem->product_id);

                    $remainingQty = $returnQty;
                    foreach ($inventoryStocks as $inventoryStock) {
                        if ($remainingQty <= 0) {
                            break;
                        }

                        $deductQty = min((int) $inventoryStock->quantity, $remainingQty);
                        if ($deductQty > 0) {
                            $prevQty = (int) $inventoryStock->quantity;
                            $inventoryStock->decrement('quantity', $deductQty);

                            // Audit trail for the returned-to-supplier deduction.
                            InventoryAdjustment::create([
                                'inventory_stocks_id' => $inventoryStock->id,
                                'previous_quantity' => $prevQty,
                                'new_quantity' => $prevQty - $deductQty,
                                'reason' => 'Returned to supplier (Stock Return #'.$stockReturn->id.')',
                                'type' => 'return_out',
                                'adjustment_date' => now()->format('Y-m-d'),
                                'adjusted_by_id' => Auth::id(),
                            ]);

                            $remainingQty -= $deductQty;
                        }
                    }

                    $poItem->decrement('received_quantity', $returnQty);
                    $this->notificationService->checkAndNotifyLowStock($poItem->product_id, $previousTotal);
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
                // Post the accounting reversal: Dr Accounts Payable / Cr Inventory,
                // so returning stock to the supplier reduces both the inventory
                // asset and the payable on the books.
                $this->journalEntryService->recordStockReturnEntry($stockReturn);

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
                'items.receivedBy',
                'logs.user',
                'status',
                'createdBy',
                'approvedBy',
                'voidedBy',
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
            $stockReturn = StockReturn::with(['status'])
                ->lockForUpdate()
                ->findOrFail($stockReturnId);

            $approvedStatusId = $this->getStatusIdBySlug('approved');
            if (! $approvedStatusId) {
                $this->fail('Approved status is not configured.');
            }

            if ((int) $stockReturn->status_id !== (int) $approvedStatusId) {
                $this->fail('Only approved stock returns can receive returned items.');
            }

            $stockReturnItem = StockReturnItem::with(['purchaseOrderItem.product', 'status'])
                ->where('stock_return_id', $stockReturn->id)
                ->where('id', $itemId)
                ->lockForUpdate()
                ->firstOrFail();

            // Prevent re-processing an item that was already fully resolved (would
            // otherwise re-add replacement stock on every submission). Items that
            // are only partially received stay 'pending' and can be received again.
            if (in_array(optional($stockReturnItem->status)->slug, ['replaced', 'loss'], true)) {
                $this->fail('This return item has already been fully received and cannot be processed again.');
            }

            // Amounts on the request are for THIS submission only; accumulate them
            // on top of whatever was already received in prior partial submissions.
            $incomingReplacedQty = (int) $request->replaced_quantity;
            $incomingLossQty = (int) $request->loss_quantity;
            if ($incomingReplacedQty < 0 || $incomingLossQty < 0) {
                $this->fail('Replacement and loss quantity must be 0 or greater.');
            }

            $requestedQty = (int) $stockReturnItem->quantity;
            $previouslyReceivedQty = (int) $stockReturnItem->returned_quantity;
            $remainingQty = $requestedQty - $previouslyReceivedQty;
            $incomingQty = $incomingReplacedQty + $incomingLossQty;
            if ($incomingQty > $remainingQty) {
                $this->fail("Replacement plus loss quantity cannot be greater than the remaining returned quantity ({$remainingQty}).");
            }

            $replacedQty = $incomingReplacedQty; // this call's replacement amount, used for the inventory/PO increments below
            $newReplacedTotal = (int) $stockReturnItem->replaced_quantity + $incomingReplacedQty;
            $newLossTotal = (int) $stockReturnItem->loss_quantity + $incomingLossQty;
            $actualReceivedQty = $newReplacedTotal + $newLossTotal;

            $remarks = trim((string) ($request->remarks ?? ''));
            $stockReturnItem->returned_quantity = $actualReceivedQty;
            $stockReturnItem->replaced_quantity = $newReplacedTotal;
            $stockReturnItem->loss_quantity = $newLossTotal;
            $pendingStatusId = $this->getStatusIdBySlug('pending');
            $partialStatusId = $this->getStatusIdBySlug('partial');
            $replacedStatusId = $this->getStatusIdBySlug('replaced');
            $lossStatusId = $this->getStatusIdBySlug('loss');
            if (! $pendingStatusId || ! $partialStatusId || ! $replacedStatusId || ! $lossStatusId) {
                $this->fail('Receive statuses are not configured.');
            }
            if ($actualReceivedQty < $requestedQty) {
                // Still has a remaining quantity to receive — keep it open for a future submission.
                $stockReturnItem->status_id = $pendingStatusId;
            } elseif ($newReplacedTotal > 0) {
                $stockReturnItem->status_id = $replacedStatusId;
            } else {
                $stockReturnItem->status_id = $lossStatusId;
            }
            $stockReturnItem->remarks = $remarks !== '' ? $remarks : $stockReturnItem->remarks;
            $stockReturnItem->received_by_id = Auth::id();
            $stockReturnItem->received_at = now();

            if ($replacedQty > 0) {
                $poItem = PurchaseOrderItem::lockForUpdate()->find($stockReturnItem->po_item_id);
                if (! $poItem) {
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

                if (! $inventoryStock) {
                    $this->fail('No inventory stock record found for this purchase order item.');
                }

                $prevInvQty = (int) $inventoryStock->quantity;
                $inventoryStock->quantity = $prevInvQty + $replacedQty;
                $inventoryStock->save();

                // Audit trail for the replacement stock added back.
                InventoryAdjustment::create([
                    'inventory_stocks_id' => $inventoryStock->id,
                    'previous_quantity' => $prevInvQty,
                    'new_quantity' => $prevInvQty + $replacedQty,
                    'reason' => 'Replacement received from supplier (Stock Return #'.$stockReturn->id.', Item #'.$stockReturnItem->id.')',
                    'type' => 'return_replacement',
                    'adjustment_date' => now()->format('Y-m-d'),
                    'adjusted_by_id' => Auth::id(),
                ]);

                PurchaseOrderLog::create([
                    'po_id' => $stockReturn->po_id,
                    'user_id' => Auth::id(),
                    'action' => 'Replacement Received',
                    'remarks' => "Returned stocks received for replacement (Stock Return #{$stockReturn->id}, Item #{$stockReturnItem->id}) qty: {$replacedQty}.",
                ]);

                // Post the accounting mirror: Dr Inventory / Cr Accounts Payable
                // for the replacement value, restoring both sides of the books.
                $this->journalEntryService->recordStockReplacementEntry(
                    $stockReturn,
                    (float) $replacedQty * (float) $poItem->unit_cost
                );
            }

            $stockReturnItem->save();

            $productName = $stockReturnItem->purchaseOrderItem?->product?->name ?? "Item #{$stockReturnItem->id}";
            StockReturnLog::create([
                'stock_return_id' => $stockReturn->id,
                'user_id' => Auth::id(),
                'action' => 'Item Received',
                'remarks' => "Received return item {$productName}: replaced {$replacedQty}, loss {$incomingLossQty}, total {$actualReceivedQty}."
                    .($remarks !== '' ? " {$remarks}" : ''),
            ]);

            $hasUnprocessedItems = StockReturnItem::where('stock_return_id', $stockReturn->id)
                ->whereNotIn('status_id', [$replacedStatusId, $lossStatusId])
                ->exists();

            if (! $hasUnprocessedItems) {
                $completedStatusId = $this->getStatusIdBySlug('completed');
                if (! $completedStatusId) {
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
                'voidedBy',
            ]);
        });

        return [
            'data' => new StockReturnResource($data),
            'message' => 'Return item received successfully!',
            'info' => "You've successfully recorded the received return item.",
            'status' => true,
        ];
    }

    public function void($request, $id)
    {
        $data = DB::transaction(function () use ($request, $id) {
            $stockReturn = StockReturn::with(['items', 'status'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($stockReturn->voided_at) {
                $this->fail('This stock return has already been voided.');
            }

            $completedStatusId = $this->getStatusIdBySlug('completed');
            if ((int) $stockReturn->status_id === (int) $completedStatusId) {
                $this->fail('This stock return has already been completed and cannot be voided.');
            }

            $hasReceivedItems = $stockReturn->items->contains(function ($item) {
                return (int) $item->returned_quantity > 0;
            });
            if ($hasReceivedItems) {
                $this->fail('This stock return already has received items and cannot be voided. Reverse those returns first.');
            }

            $trimmedReason = trim((string) ($request->reason ?? ''));
            if ($trimmedReason === '') {
                $this->fail('A reason is required to void this stock return.');
            }

            $approvedStatusId = $this->getStatusIdBySlug('approved');
            $wasApproved = (int) $stockReturn->status_id === (int) $approvedStatusId;

            if (! $wasApproved) {
                // Still pending/disapproved — nothing has touched inventory or the books
                // yet, so there is nothing to reverse. Just remove the request outright
                // (items/logs cascade-delete with it) instead of leaving a voided husk.
                $stockReturnId = $stockReturn->id;
                $poId = $stockReturn->po_id;
                $stockReturn->delete();

                PurchaseOrderLog::create([
                    'po_id' => $poId,
                    'user_id' => Auth::id(),
                    'action' => 'Stock Return Deleted',
                    'remarks' => "Stock return #{$stockReturnId} deleted. Reason: {$trimmedReason}",
                ]);

                return null;
            }

            // Reverse exactly what was deducted on approval, using that step's own
            // audit trail so restored quantities land back in their original batches.
            $adjustments = InventoryAdjustment::where('type', 'return_out')
                ->where('reason', 'Returned to supplier (Stock Return #'.$stockReturn->id.')')
                ->lockForUpdate()
                ->get();

            foreach ($adjustments as $adjustment) {
                $inventoryStock = InventoryStocks::lockForUpdate()->find($adjustment->inventory_stocks_id);
                if (! $inventoryStock) {
                    continue;
                }

                $restoreQty = (int) $adjustment->previous_quantity - (int) $adjustment->new_quantity;
                if ($restoreQty <= 0) {
                    continue;
                }

                $prevQty = (int) $inventoryStock->quantity;
                $inventoryStock->increment('quantity', $restoreQty);

                InventoryAdjustment::create([
                    'inventory_stocks_id' => $inventoryStock->id,
                    'previous_quantity' => $prevQty,
                    'new_quantity' => $prevQty + $restoreQty,
                    'reason' => 'Stock return #'.$stockReturn->id.' voided: '.$trimmedReason,
                    'type' => 'void',
                    'adjustment_date' => now()->format('Y-m-d'),
                    'adjusted_by_id' => Auth::id(),
                ]);
            }

            foreach ($stockReturn->items as $item) {
                $poItem = PurchaseOrderItem::lockForUpdate()->find($item->po_item_id);
                if ($poItem) {
                    $poItem->increment('received_quantity', (int) $item->quantity);
                }
            }

            $this->journalEntryService->reverseEntriesForSource(
                $stockReturn,
                'Stock return voided. Reason: '.$trimmedReason,
                now()->toDateString()
            );

            PurchaseOrderLog::create([
                'po_id' => $stockReturn->po_id,
                'user_id' => Auth::id(),
                'action' => 'Stock Return Voided',
                'remarks' => "Stock return #{$stockReturn->id} voided; returned stock restored to inventory.",
            ]);

            $voidedStatusId = $this->getStatusIdBySlug('voided');
            if (! $voidedStatusId) {
                $this->fail('Voided status is not configured.');
            }

            $stockReturn->status_id = $voidedStatusId;
            $stockReturn->voided_at = now();
            $stockReturn->void_reason = $trimmedReason;
            $stockReturn->voided_by_id = Auth::id();
            $stockReturn->save();

            StockReturnLog::create([
                'stock_return_id' => $stockReturn->id,
                'user_id' => Auth::id(),
                'action' => 'Voided',
                'remarks' => $trimmedReason,
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
                'voidedBy',
            ]);
        });

        if ($data === null) {
            return [
                'data' => null,
                'deleted' => true,
                'message' => 'Stock return deleted successfully!',
                'info' => "You've successfully deleted the pending stock return.",
                'status' => true,
            ];
        }

        return [
            'data' => new StockReturnResource($data),
            'deleted' => false,
            'message' => 'Stock return voided successfully!',
            'info' => "You've successfully voided the stock return.",
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
