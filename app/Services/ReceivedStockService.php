<?php

namespace App\Services;

use App\Models\PurchaseOrderItem;
use App\Models\ReceivedStock;
use App\Models\ReceivedItem;
use App\Models\PurchaseOrder;
use App\Models\ListStatus;
use Illuminate\Support\Facades\DB;

class ReceivedStockService
{
    public function getAll()
    {
        return ReceivedStock::with(['purchaseOrder', 'supplier', 'items'])->get();
    }

    public function getById($id)
    {
        return ReceivedStock::with(['purchaseOrder', 'supplier', 'items'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $receivedStock = ReceivedStock::create([
                'po_id' => $data['po_id'],
                'supplier_id' => $data['supplier_id'],
                'received_date' => $data['received_date'],
                'batch_code' => $data['batch_code'],
            ]);

            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    ReceivedItem::create([
                        'received_id' => $receivedStock->id,
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_cost' => $itemData['unit_cost'],
                        'total_cost' => $itemData['total_cost'],
                        'po_item_id' => $itemData['po_item_id'],
                    ]);

                    $po_item = PurchaseOrderItem::find($itemData['po_item_id']);
                    if ($po_item) {
                        $po_item->status = 'received';
                        $po_item->update();
                    }
                }
            }

            // Check if all PurchaseOrderItems for this po_id are 'received'
            $allReceived = PurchaseOrderItem::where('po_id', $data['po_id'])->where('status', '!=', 'received')->count() == 0;
            if ($allReceived) {
                $completedStatus = ListStatus::where('name', 'completed')->first();
                if ($completedStatus) {
                    $purchaseOrder = PurchaseOrder::find($data['po_id']);
                    if ($purchaseOrder) {
                        $purchaseOrder->status_id = $completedStatus->id;
                        $purchaseOrder->update();
                    }
                }
            }

            return $receivedStock->load(['purchaseOrder', 'supplier', 'items']);
        });
    }

    public function update($id, array $data)
    {
        $receivedStock = ReceivedStock::findOrFail($id);
        $receivedStock->update($data);
        return $receivedStock->load(['purchaseOrder', 'supplier', 'items']);
    }

    public function delete($id)
    {
        $receivedStock = ReceivedStock::findOrFail($id);
        $receivedStock->delete();
    }

    public function getNextBatchCode()
    {
        $latestBatchCode = ReceivedStock::orderBy('batch_code', 'desc')->value('batch_code');

        if (!$latestBatchCode) {
            return 'BATCH-001';
        }

        $parts = explode('-', $latestBatchCode);
        if (count($parts) == 2 && $parts[0] == 'BATCH') {
            $number = (int) $parts[1];
            $nextNumber = $number + 1;
            return 'BATCH-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }

        return 'BATCH-001';
    }
}
