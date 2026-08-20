<?php

namespace App\Services\System\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\System\PurchaseOrder\PurchaseOrderResource;
use App\Http\Resources\System\PurchaseOrder\ViewResource;
use App\Models\ListStatus;
use App\Models\PurchaseOrderLog;
use App\Services\SeriesService;

class PurchaseOrderClass
{
    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) { 
        $this->series_service = $series_service;
    }

    public function list($request)
    {
        $data = PurchaseOrder::with([
            'status',
            'supplier',
            'approved_by',
            'items.product',
            'items.receivedItems.inventoryStocks',
            'items.receivedItems.receivedStock',
        ])
            ->latest('id')
            ->paginate($request->count ?? 10);
        return PurchaseOrderResource::collection($data);
    }

    public function view($id)
    {
        $data = PurchaseOrder::with([
            'status',
            'supplier',
            'approved_by',
            'items.product',
            'items.receivedItems.inventoryStocks',
            'items.receivedItems.receivedStock',
            'logs',
        ])->findOrFail($id);
        return new PurchaseOrderResource($data);
    }

    public function save($request)
    {
        $data = DB::transaction(function () use ($request) {
            $poData = [
                'pr_number' => $this->series_service->get('purchase_request'),
                'po_date' => now(),
                'total_amount' => $request->total_amount,
                'status_id' => ListStatus::where('name', 'Pending')->first()->id,
                'supplier_id' => $request->supplier_id,
                'created_by_id' => Auth::id(),
            ];

            $purchaseOrder = PurchaseOrder::create($poData);

            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'po_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['total_cost'],
                ]);
            }

            PurchaseOrderLog::create([
                'po_id' => $purchaseOrder->id,
                'user_id' => Auth::id(),
                'action' => 'Created',
                'remarks' => 'Purchase order created.',
            ]);

            return $purchaseOrder->load(['status', 'supplier', 'items.product', 'logs']);
        });

        return [
            'data' => new PurchaseOrderResource($data),
            'message' => 'Purchase order created successfully!',
            'info' => "You've successfully created a new purchase order."
        ];
    }

    public function status($request)
    {
        $data = PurchaseOrder::findOrFail($request->id);
        $data->status_id = $request->status_id;
        $data->save();

        return [
            'data' => new PurchaseOrderResource($data),
            'message' => 'Purchase order status updated successfully!',
            'info' => "You've successfully updated the purchase order status."
        ];
    }

    public function updateStatus($request)
    {
        $data = DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::findOrFail($request->id);
            $oldStatus = $purchaseOrder->status->name;
            $newStatus = ListStatus::findOrFail($request->status_id)->name;

            if($newStatus == 'Approved'){
                $purchaseOrder->po_number = $this->series_service->get('purchase_order');
                $purchaseOrder->approved_by_id = Auth::id();
                $purchaseOrder->approved_date = now();
            }
            $purchaseOrder->status_id = $request->status_id;
            $purchaseOrder->save();

            PurchaseOrderLog::create([
                'po_id' => $purchaseOrder->id,
                'user_id' => Auth::id(),
                'action' => $newStatus,
                'remarks' => $request->remarks,
            ]);

            return $purchaseOrder->load(['status', 'supplier', 'items.product', 'logs']);
        });

        return [
            'data' => new PurchaseOrderResource($data),
            'message' => 'Purchase order status updated successfully!',
            'info' => "You've successfully updated the purchase order status."
        ];
    }

    public function void($request)
    {
        $data = DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::with(['status', 'items.receivedItems'])->findOrFail($request->id);
            $voidedStatusId = ListStatus::getBySlug('voided')?->id;

            if (optional($purchaseOrder->status)->slug === 'voided') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'void' => ['This Purchase Order has already been voided.'],
                ]);
            }

            if (!$purchaseOrder->approved_by_id || $purchaseOrder->approved_by_id !== Auth::id()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'void' => ['Only the approver who approved this purchase order can void it.'],
                ]);
            }

            $hasReceivedItems = $purchaseOrder->items->contains(fn($item) => $item->receivedItems->isNotEmpty());
            if ($hasReceivedItems) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'void' => ['This Purchase Order already has received stock and cannot be voided. Process a stock return instead.'],
                ]);
            }

            $purchaseOrder->status_id = $voidedStatusId;
            $purchaseOrder->save();

            PurchaseOrderLog::create([
                'po_id' => $purchaseOrder->id,
                'user_id' => Auth::id(),
                'action' => 'Voided',
                'remarks' => $request->remarks,
            ]);

            return $purchaseOrder->load(['status', 'supplier', 'items.product', 'logs']);
        });

        return [
            'data' => new PurchaseOrderResource($data),
            'message' => 'Purchase order voided successfully!',
            'info' => "You've successfully voided the purchase order."
        ];
    }

    public function update($request)
    {
        $data = DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::with(['status', 'items.receivedItems'])->findOrFail($request->id);

            \App\Support\RecordLock::assertEditable($purchaseOrder, 'This purchase order', 'edited');

            // Editing rebuilds the item list (delete + recreate), which would
            // orphan any received stock and its inventory links. Block once
            // anything has been received.
            $hasReceivedItems = $purchaseOrder->items->contains(fn ($item) => $item->receivedItems->isNotEmpty());
            if ($hasReceivedItems) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => ['This purchase order already has received stock and can no longer be edited. Process a stock return instead.'],
                ]);
            }

            $poData = [
                'total_amount' => $request->total_amount,
                'supplier_id' => $request->supplier_id,
            ];

            $purchaseOrder->update($poData);

            // Delete existing items and recreate
            $purchaseOrder->items()->delete();

            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'po_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['total_cost'],
                ]);
            }

            PurchaseOrderLog::create([
                'po_id' => $purchaseOrder->id,
                'user_id' => Auth::id(),
                'action' => 'Updated',
                'remarks' => 'Purchase order updated.',
            ]);

            return $purchaseOrder->load(['status', 'supplier', 'items.product']);
        });

        return [
            'data' => new PurchaseOrderResource($data),
            'message' => 'Purchase order updated successfully!',
            'info' => "You've successfully updated the purchase order."
        ];
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $purchaseOrder = PurchaseOrder::with(['status', 'items.receivedItems'])->findOrFail($id);

            \App\Support\RecordLock::assertEditable($purchaseOrder, 'This purchase order', 'deleted', 'delete');

            // Deleting a PO with received stock would orphan the received
            // records and inventory. Steer to void / stock-return instead.
            $hasReceivedItems = $purchaseOrder->items->contains(fn ($item) => $item->receivedItems->isNotEmpty());
            if ($hasReceivedItems) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'delete' => ['This purchase order has received stock and cannot be deleted. Void it or process a stock return instead.'],
                ]);
            }

            $purchaseOrder->delete();

            return [
                'message' => 'Purchase order deleted successfully!',
                'info' => "You've successfully deleted the purchase order."
            ];
        });
    }

    public function generatePoNumber()
    {
        // Preview the exact number the 'purchase_order' series will assign on
        // approval, without consuming it — so the preview matches what is saved
        // (previously this string-parsed po_number and diverged from the series).
        return $this->series_service->peek('purchase_order');
    }
}
