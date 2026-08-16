<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedStock;
use App\Models\ReceivedItem;
use App\Models\PurchaseOrder;
use App\Models\ListStatus;
use App\Models\PurchaseOrderLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
        return ReceivedStock::with(['purchaseOrder', 'supplier', 'items.product.brand', 'items.product.unit', 'items.product.packaging', 'receivedBy', 'voidedBy', 'payments.createdBy'])->get();
    }

    public function getById($id)
    {
        return ReceivedStock::with(['purchaseOrder', 'supplier', 'items.product.brand', 'items.product.unit', 'items.product.packaging', 'receivedBy', 'voidedBy', 'payments.createdBy'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $paymentMode = $data['payment_mode'] ?? 'Credit';
            $amountPaid = $paymentMode === 'Credit'
                ? 0
                : round((float) ($data['amount_paid'] ?? 0), 2);
            $isBankTransfer  = $paymentMode === 'Bank Transfer';
            $isCheck         = $paymentMode === 'Check';
            $bankAccountId   = $isBankTransfer ? (int) ($data['bank_account_id'] ?? 0) ?: null : null;
            $bankName        = $isBankTransfer ? trim((string) ($data['bank_name'] ?? '')) : null;
            $referenceNumber = ($isBankTransfer || $isCheck) ? trim((string) ($data['reference_number'] ?? '')) : null;
            $dueDate         = $paymentMode === 'Credit' ? ($data['due_date'] ?? null) : null;

            $receivedStock = ReceivedStock::create([
                'po_id' => $data['po_id'],
                'supplier_id' => $data['supplier_id'],
                'received_date' => !empty($data['received_date']) ? Carbon::parse($data['received_date']) : Carbon::now(),
                'received_no' => $this->series_service->get('received_no'),
                'payment_mode' => $paymentMode,
                'due_date' => $dueDate,
                'amount_paid' => $amountPaid,
                'bank_account_id' => $bankAccountId,
                'bank_name' => $bankName,
                'reference_number' => $referenceNumber,
                'received_by_id' => Auth::id(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            if ($paymentMode !== 'Credit' && $amountPaid > 0) {
                $receivedStock->payments()->create([
                    'payment_date' => Carbon::now()->toDateString(),
                    'payment_mode' => $paymentMode,
                    'amount_paid' => $amountPaid,
                    'bank_account_id' => $bankAccountId,
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
            if (($paymentMode === 'Bank Transfer' || $paymentMode === 'Check') && $referenceNumber) {
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
        return DB::transaction(function () use ($id, $data) {
            $receivedStock = ReceivedStock::with('payments')->findOrFail($id);
            $this->journalEntryService->reverseEntriesForSource($receivedStock, 'Received stock updated. Previous purchase receipt entry reversed.', $data['received_date'] ?? now()->toDateString());

            foreach ($receivedStock->payments as $payment) {
                $this->journalEntryService->reverseEntriesForSource(
                    $payment,
                    'Received stock updated. Previous payment entry reversed.',
                    $data['received_date'] ?? now()->toDateString()
                );
            }
            $receivedStock->payments()->delete();

            if (!isset($data['payment_mode']) || !$data['payment_mode']) {
                $data['payment_mode'] = $receivedStock->payment_mode ?? 'Credit';
            }
            if ($data['payment_mode'] === 'Credit') {
                $data['amount_paid'] = 0;
                $data['bank_name'] = null;
                $data['reference_number'] = null;
                $data['due_date'] = $data['due_date'] ?? $receivedStock->due_date;
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
            } elseif ($data['payment_mode'] === 'Check') {
                $data['bank_name'] = null;
                $data['reference_number'] = isset($data['reference_number']) && trim((string) $data['reference_number']) !== ''
                    ? trim((string) $data['reference_number'])
                    : (($receivedStock->payment_mode ?? null) === 'Check' ? $receivedStock->reference_number : null);
            } elseif ($data['payment_mode'] !== 'Credit') {
                $data['bank_name'] = null;
                $data['reference_number'] = null;
            }
            if ($data['payment_mode'] !== 'Credit') {
                $data['due_date'] = null;
            }
            $receivedStock->update($data);

            $paymentMode = $receivedStock->payment_mode;
            $amountPaid = round((float) ($receivedStock->amount_paid ?? 0), 2);
            if ($paymentMode !== 'Credit' && $amountPaid > 0) {
                $receivedStock->payments()->create([
                    'payment_date' => $receivedStock->received_date,
                    'payment_mode' => $paymentMode,
                    'amount_paid' => $amountPaid,
                    'bank_name' => $receivedStock->bank_name,
                    'reference_number' => $receivedStock->reference_number,
                    'created_by_id' => Auth::id(),
                ]);
            }

            $receivedStock = $receivedStock->load(['purchaseOrder', 'supplier', 'items', 'receivedBy', 'payments.createdBy']);
            $this->journalEntryService->recordReceivedStockEntry($receivedStock);

            return $receivedStock;
        });
    }

    public function applyPayment(ReceivedStock $receivedStock, array $data)
    {
        return DB::transaction(function () use ($receivedStock, $data) {
            $receivedStock->loadMissing(['items', 'purchaseOrder', 'supplier', 'receivedBy', 'payments.createdBy']);

            if ($receivedStock->voided_at) {
                throw ValidationException::withMessages([
                    'received_stock' => 'This received stock has been voided and can no longer accept payments.',
                ]);
            }

            $totalAmount = round((float) $receivedStock->items->sum('total_cost'), 2);
            $currentPaid = round((float) ($receivedStock->amount_paid ?? 0), 2);
            $paymentAmount = round((float) ($data['payment_amount'] ?? 0), 2);
            $newAmountPaid = min(round($currentPaid + $paymentAmount, 2), $totalAmount);

            $payMode  = $data['payment_mode'] ?? 'Cash on Hand';
            $isBT     = $payMode === 'Bank Transfer';
            $isCheck  = $payMode === 'Check';

            $payment = $receivedStock->payments()->create([
                'payment_date'       => Carbon::now()->toDateString(),
                'payment_mode'       => $payMode,
                'amount_paid'        => $paymentAmount,
                'bank_account_id'    => $isBT ? ((int) ($data['bank_account_id'] ?? 0) ?: null) : null,
                'bank_name'          => $isBT ? trim((string) ($data['bank_name'] ?? '')) : null,
                'reference_number'   => ($isBT || $isCheck) ? trim((string) ($data['reference_number'] ?? '')) : null,
                'created_by_id'      => Auth::id(),
            ]);

            $isFullySettled = $newAmountPaid >= $totalAmount;
            $receivedStock->update([
                'amount_paid' => $newAmountPaid,
                'payment_mode' => $isFullySettled
                    ? ($data['payment_mode'] ?? $receivedStock->payment_mode)
                    : $receivedStock->payment_mode,
            ]);

            $payment->load('createdBy');
            $receivedStock->load(['purchaseOrder', 'supplier', 'items', 'receivedBy', 'payments.createdBy']);
            $this->journalEntryService->recordReceivedStockPaymentEntry($receivedStock, $payment);

            return $receivedStock;
        });
    }

    public function void($id, $reason = null)
    {
        return DB::transaction(function () use ($id, $reason) {
            $receivedStock = ReceivedStock::with(['payments', 'items.inventoryStocks'])->findOrFail($id);

            if ($receivedStock->voided_at) {
                throw ValidationException::withMessages([
                    'received_stock' => 'This received stock has already been voided.',
                ]);
            }

            $trimmedReason = trim((string) $reason);
            if ($trimmedReason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required to void this received stock.',
                ]);
            }

            // Block voiding once any of this receipt's stock has been consumed
            // (sold, converted, or adjusted down). Voiding then would leave
            // inventory and accounting inconsistent — those movements must be
            // reversed first.
            foreach ($receivedStock->items as $item) {
                foreach ($item->inventoryStocks as $stock) {
                    if ((int) $stock->quantity < (int) $item->quantity) {
                        throw ValidationException::withMessages([
                            'received_stock' => 'This received stock cannot be voided because some of its stock has already been sold, converted, or adjusted. Reverse those movements first.',
                        ]);
                    }
                }
            }

            foreach ($receivedStock->payments as $payment) {
                $this->journalEntryService->reverseEntriesForSource($payment, 'Supplier payment reversed — received stock voided. Reason: ' . $trimmedReason, now()->toDateString());
            }
            $this->journalEntryService->reverseEntriesForSource($receivedStock, 'Received stock voided. Reason: ' . $trimmedReason, now()->toDateString());

            // Pull the stock this receiving added back out of inventory (audited),
            // rather than cascade-deleting the batch rows — the received_stock
            // record itself is kept for the audit trail instead of being removed.
            foreach ($receivedStock->items as $item) {
                foreach ($item->inventoryStocks as $stock) {
                    $previousQuantity = (int) $stock->quantity;
                    if ($previousQuantity <= 0) {
                        continue;
                    }

                    $stock->quantity = 0;
                    $stock->save();

                    InventoryAdjustment::create([
                        'inventory_stocks_id' => $stock->id,
                        'previous_quantity'   => $previousQuantity,
                        'new_quantity'        => 0,
                        'reason'              => 'Received stock #' . $receivedStock->received_no . ' voided: ' . $trimmedReason,
                        'type'                => 'void',
                        'adjustment_date'     => now()->format('Y-m-d'),
                        'adjusted_by_id'      => Auth::id(),
                    ]);
                }
            }

            $receivedStock->update([
                'voided_at' => now(),
                'void_reason' => $trimmedReason,
                'voided_by_id' => Auth::id(),
            ]);
        });
    }

    public function getNextBatchCode()
    {
        // Preview the exact code the batch_code series will assign next, without
        // consuming it — so the receiving screen shows the same code that
        // create() actually saves. (Previously this used a separate BATCH-###
        // scheme that diverged from the real series value.)
        return $this->series_service->peek('batch_code');
    }
}
