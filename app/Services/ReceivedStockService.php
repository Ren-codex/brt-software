<?php

namespace App\Services;

use App\Models\InventoryStocks;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedStock;
use App\Models\ReceivedItem;
use App\Models\PurchaseOrder;
use App\Models\ListStatus;
use App\Models\PurchaseOrderLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\SeriesService;
use Carbon\Carbon;

class ReceivedStockService
{
    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) { 
        $this->series_service = $series_service;
    }

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
                'received_date' => Carbon::now(),
                'batch_code' => $this->series_service->get('batch_code'),
                'received_by_id' => Auth::id(),
            ]);

            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    if ($itemData['to_received_quantity'] > 0) {
                        // Log the stock receipt for this item
                        PurchaseOrderLog::create([
                            'po_id' => $data['po_id'],
                            'user_id' => Auth::id(),
                            'action' => 'Received',
                            'remarks' => 'Received ' . $itemData['to_received_quantity'] . ' stocks of product (' . $itemData['product_name'] . ') with batch code: ' . $receivedStock->batch_code,
                        ]);

                        $receivedItem = ReceivedItem::create([
                            'received_id' => $receivedStock->id,
                            'product_id' => $itemData['product_id'],
                            'quantity' => $itemData['to_received_quantity'],
                            'unit_cost' => $itemData['unit_cost'],
                            'total_cost' => $itemData['total_cost'],
                            'po_item_id' => $itemData['po_item_id'],
                        ]);

                        $po_item = PurchaseOrderItem::find($itemData['po_item_id']);
                        if ($po_item) {
                            if($po_item->received_quantity + $itemData['to_received_quantity'] >= $po_item->quantity){
                                $po_item->status = 'received';
                            }
                            $po_item->received_quantity += $itemData['to_received_quantity'];
                            $po_item->update();
                        }
                        
                        InventoryStocks::create([
                            'received_item_id' => $receivedItem->id,
                            'quantity' => $itemData['to_received_quantity'],
                        ]);
                    }
                }
            }

            // Check if all PurchaseOrderItems for this po_id are 'received'
            $allReceived = PurchaseOrderItem::where('po_id', $data['po_id'])->where('status', '!=', 'received')->count() == 0;
            if ($allReceived) {
                $completedStatus = ListStatus::where('slug', 'completed')->first();
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
