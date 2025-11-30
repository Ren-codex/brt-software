<?php

namespace App\Services\System\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\System\PurchaseOrder\PurchaseOrderResource;
use App\Http\Resources\System\PurchaseOrder\ViewResource;
use App\Models\ListStatus;

class PurchaseOrderClass
{
    public function list($request)
    {
        $data = PurchaseOrder::with(['status', 'supplier', 'items.product'])->paginate($request->count ?? 10);
        return PurchaseOrderResource::collection($data);
    }

    public function view($id)
    {
        $data = PurchaseOrder::with(['status', 'supplier', 'items.product'])->findOrFail($id);
        return new ViewResource($data);
    }

    public function save($request)
    {
        $data = DB::transaction(function () use ($request) {
            $poData = [
                'po_number' => $this->generatePoNumber(),
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

            return $purchaseOrder->load(['status', 'supplier', 'items.product']);
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

    public function update($request)
    {
        $data = DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::findOrFail($request->id);

            $poData = [
                'total_amount' => $request->total_amount,
                'status_id' => $request->status_id,
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
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        $purchaseOrder->delete();

        return [
            'message' => 'Purchase order deleted successfully!',
            'info' => "You've successfully deleted the purchase order."
        ];
    }

    public function generatePoNumber()
    {
        $year = date('Y');
        $lastPo = PurchaseOrder::whereYear('po_date', $year)->orderBy('id', 'desc')->first();
        $nextNumber = $lastPo ? intval(substr($lastPo->po_number, -4)) + 1 : 1;
        return 'PO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
