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
use App\Services\Accounting\JournalEntryService;
use App\Services\SeriesService;
use Carbon\Carbon;

class ReceivedStockService
{
    protected $series_service;
    protected $journalEntryService;

    public function __construct(
        SeriesService $series_service,
        JournalEntryService $journalEntryService,
    ) { 
        $this->series_service = $series_service;
        $this->journalEntryService = $journalEntryService;
    }

    public function getAll()
    {
        return ReceivedStock::with(['purchaseOrder', 'supplier', 'items', 'receivedBy', 'payments.createdBy'])->get();
    }

    public function getById($id)
    {
        return ReceivedStock::with(['purchaseOrder', 'supplier', 'items', 'receivedBy', 'payments.createdBy'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $paymentMode = $data['payment_mode'] ?? 'Credit';
            $amountPaid = $paymentMode === 'Credit'
                ? 0
                : round((float) ($data['amount_paid'] ?? 0), 2);
            $bankName = $paymentMode === 'Bank Transfer'
                ? trim((string) ($data['bank_name'] ?? ''))
                : null;
            $referenceNumber = $paymentMode === 'Bank Transfer'
                ? trim((string) ($data['reference_number'] ?? ''))
                : null;

            $receivedStock = ReceivedStock::create([
                'po_id' => $data['po_id'],
                'supplier_id' => $data['supplier_id'],
                'received_date' => Carbon::now(),
                'received_no' => $this->series_service->get('received_no'),
                'payment_mode' => $paymentMode,
                'amount_paid' => $amountPaid,
                'bank_name' => $bankName,
                'reference_number' => $referenceNumber,
                'received_by_id' => Auth::id(),
            ]);

            if ($paymentMode !== 'Credit' && $amountPaid > 0) {
                $receivedStock->payments()->create([
                    'payment_date' => Carbon::now()->toDateString(),
                    'payment_mode' => $paymentMode,
                    'amount_paid' => $amountPaid,
                    'bank_name' => $bankName,
                    'reference_number' => $referenceNumber,
                    'created_by_id' => Auth::id(),
                ]);
            }

            $paymentDetailParts = [];
            if ($paymentMode !== 'Credit') {
                $paymentDetailParts[] = 'amount paid: ' . number_format($amountPaid, 2);
            }
            if ($paymentMode === 'Bank Transfer' && $bankName) {
                $paymentDetailParts[] = 'bank: ' . $bankName;
            }
            if ($paymentMode === 'Bank Transfer' && $referenceNumber) {
                $paymentDetailParts[] = 'reference: ' . $referenceNumber;
            }
            $paymentDetailSuffix = empty($paymentDetailParts)
                ? ''
                : ' (' . implode(', ', $paymentDetailParts) . ')';

            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    if ($itemData['to_received_quantity'] > 0) {
                        $receivedItemTotalCost = round((float) $itemData['unit_cost'] * (float) $itemData['to_received_quantity'], 2);

                        // Log the stock receipt for this item
                        PurchaseOrderLog::create([
                            'po_id' => $data['po_id'],
                            'user_id' => Auth::id(),
                            'action' => 'Received',
                            'remarks' => 'Received ' . $itemData['to_received_quantity'] . ' stocks of product (' . $itemData['product_name'] . ') with received_no: ' . $receivedStock->received_no . ' via ' . $paymentMode . $paymentDetailSuffix,
                        ]);

                        $receivedItem = ReceivedItem::create([
                            'received_id' => $receivedStock->id,
                            'product_id' => $itemData['product_id'],
                            'quantity' => $itemData['to_received_quantity'],
                            'unit_cost' => $itemData['unit_cost'],
                            'total_cost' => $receivedItemTotalCost,
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
                            'retail_price' => $itemData['retail_price'],
                            'wholesale_price' => $itemData['wholesale_price'],
                            'expiration_date' => $itemData['expiration_date'],
                            'batch_code' => $this->series_service->get('batch_code'),
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

            $receivedStock = $receivedStock->load(['purchaseOrder', 'supplier', 'items', 'receivedBy', 'payments.createdBy']);
            $this->journalEntryService->recordReceivedStockEntry($receivedStock);

            return $receivedStock;
        });
    }

    public function update($id, array $data)
    {
        $receivedStock = ReceivedStock::findOrFail($id);
        $this->journalEntryService->reverseEntriesForSource($receivedStock, 'Received stock updated. Previous purchase receipt entry reversed.', $data['received_date'] ?? now()->toDateString());
        if (!isset($data['payment_mode']) || !$data['payment_mode']) {
            $data['payment_mode'] = $receivedStock->payment_mode ?? 'Credit';
        }
        if ($data['payment_mode'] === 'Credit') {
            $data['amount_paid'] = 0;
            $data['bank_name'] = null;
            $data['reference_number'] = null;
        } elseif (!isset($data['amount_paid']) || $data['amount_paid'] === null || $data['amount_paid'] === '') {
            $data['amount_paid'] = $data['payment_mode'] === 'Credit'
                ? 0
                : ($receivedStock->amount_paid ?? 0);
        }
        if ($data['payment_mode'] === 'Bank Transfer') {
            $data['bank_name'] = isset($data['bank_name']) && trim((string) $data['bank_name']) !== ''
                ? trim((string) $data['bank_name'])
                : (($receivedStock->payment_mode ?? null) === 'Bank Transfer' ? $receivedStock->bank_name : null);
            $data['reference_number'] = isset($data['reference_number']) && trim((string) $data['reference_number']) !== ''
                ? trim((string) $data['reference_number'])
                : (($receivedStock->payment_mode ?? null) === 'Bank Transfer' ? $receivedStock->reference_number : null);
        } elseif ($data['payment_mode'] !== 'Credit') {
            $data['bank_name'] = null;
            $data['reference_number'] = null;
        }
        $receivedStock->update($data);
        $receivedStock = $receivedStock->load(['purchaseOrder', 'supplier', 'items', 'receivedBy', 'payments.createdBy']);
        $this->journalEntryService->recordReceivedStockEntry($receivedStock);

        return $receivedStock;
    }

    public function applyPayment(ReceivedStock $receivedStock, array $data)
    {
        return DB::transaction(function () use ($receivedStock, $data) {
            $receivedStock->loadMissing(['items', 'purchaseOrder', 'supplier', 'receivedBy', 'payments.createdBy']);

            $totalAmount = round((float) $receivedStock->items->sum('total_cost'), 2);
            $currentPaid = round((float) ($receivedStock->amount_paid ?? 0), 2);
            $paymentAmount = round((float) ($data['payment_amount'] ?? 0), 2);
            $newAmountPaid = min(round($currentPaid + $paymentAmount, 2), $totalAmount);

            $payment = $receivedStock->payments()->create([
                'payment_date' => Carbon::now()->toDateString(),
                'payment_mode' => $data['payment_mode'] ?? 'Cash',
                'amount_paid' => $paymentAmount,
                'bank_name' => ($data['payment_mode'] ?? 'Cash') === 'Bank Transfer'
                    ? trim((string) ($data['bank_name'] ?? ''))
                    : null,
                'reference_number' => ($data['payment_mode'] ?? 'Cash') === 'Bank Transfer'
                    ? trim((string) ($data['reference_number'] ?? ''))
                    : null,
                'created_by_id' => Auth::id(),
            ]);

            $receivedStock->update([
                'amount_paid' => $newAmountPaid,
            ]);

            $payment->load('createdBy');
            $receivedStock->load(['purchaseOrder', 'supplier', 'items', 'receivedBy', 'payments.createdBy']);
            $this->journalEntryService->recordReceivedStockPaymentEntry($receivedStock, $payment);

            return $receivedStock;
        });
    }

    public function delete($id)
    {
        $receivedStock = ReceivedStock::with('payments')->findOrFail($id);
        foreach ($receivedStock->payments as $payment) {
            $this->journalEntryService->reverseEntriesForSource($payment, 'Supplier payment deleted along with received stock.', now()->toDateString());
        }
        $this->journalEntryService->reverseEntriesForSource($receivedStock, 'Received stock deleted. Purchase receipt entry reversed.', now()->toDateString());
        $receivedStock->delete();
    }

    public function getNextBatchCode()
    {
        $latestBatchCode = InventoryStocks::orderBy('batch_code', 'desc')->value('batch_code');

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
