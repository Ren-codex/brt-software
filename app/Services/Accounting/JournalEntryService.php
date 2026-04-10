<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Expense;
use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\SalesOrder;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class JournalEntryService
{
    private const JOURNAL_REVERSAL_COLUMNS = [
        'reversal_of_id',
        'reversed_at',
        'reversal_reason',
    ];

    private ?array $journalEntryColumns = null;

    public function recordSaleEntries(SalesOrder $salesOrder): array
    {
        $salesOrder->loadMissing(['items']);

        $revenueAmount = round((float) $salesOrder->total_amount, 2);
        $costAmount = round($this->calculateCostOfGoodsSold($salesOrder), 2);
        $paymentMode = strtolower((string) $salesOrder->payment_mode);
        $isCreditSale = in_array($paymentMode, ['credit', 'credit sales'], true);

        $cashOrReceivableAccount = $isCreditSale
            ? $this->ensureAccount('1100', 'accounts_receivable', 'Accounts Receivable', 'asset', 'current_asset')
            : $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset');

        $salesAccount = $this->ensureAccount('4100', 'rice_sales', 'Rice Sales', 'revenue', 'sales');
        $cogsAccount = $this->ensureAccount('5100', 'cost_of_goods_sold', 'Cost of Goods Sold', 'expense', 'cost_of_sales');
        $inventoryAccount = $this->ensureAccount('1200', 'rice_inventory', 'Rice Inventory', 'asset', 'inventory');

        $entries = [];

        $entries[] = $this->createEntry(
            $salesOrder,
            $salesOrder->order_date,
            'sales_revenue',
            'Auto-generated from saved sale. Revenue recognition entry.',
            [
                [
                    'account_id' => $cashOrReceivableAccount->id,
                    'line_type' => 'debit',
                    'amount' => $revenueAmount,
                    'description' => $isCreditSale ? 'Recognize accounts receivable from sale.' : 'Recognize cash received from sale.',
                ],
                [
                    'account_id' => $salesAccount->id,
                    'line_type' => 'credit',
                    'amount' => $revenueAmount,
                    'description' => 'Recognize rice sales revenue.',
                ],
            ]
        );

        if ($costAmount > 0) {
            $entries[] = $this->createEntry(
                $salesOrder,
                $salesOrder->order_date,
                'inventory_out',
                'Auto-generated from saved sale. Inventory and cost recognition entry.',
                [
                    [
                        'account_id' => $cogsAccount->id,
                        'line_type' => 'debit',
                        'amount' => $costAmount,
                        'description' => 'Recognize cost of goods sold.',
                    ],
                    [
                        'account_id' => $inventoryAccount->id,
                        'line_type' => 'credit',
                        'amount' => $costAmount,
                        'description' => 'Reduce rice inventory for the sold quantity.',
                    ],
                ]
            );
        }

        return $entries;
    }

    public function recordReceiptEntry(Receipt $receipt): ?JournalEntry
    {
        $receipt->loadMissing(['arInvoice.sales_order']);

        $amount = round((float) $receipt->amount_paid, 2);
        if ($amount <= 0) {
            return null;
        }

        $cashAccount = $this->resolveCashAccountByPaymentMode($receipt->payment_mode);
        $receivableAccount = $this->ensureAccount('1100', 'accounts_receivable', 'Accounts Receivable', 'asset', 'current_asset');
        $sourceSalesOrder = optional(optional($receipt->arInvoice)->sales_order);
        $memo = 'Receipt ' . $receipt->receipt_number . ' applied to invoice collection.';

        return $this->createEntry(
            $receipt,
            $receipt->receipt_date,
            'receipt_collection',
            $memo,
            [
                [
                    'account_id' => $cashAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Record customer collection.',
                ],
                [
                    'account_id' => $receivableAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Reduce accounts receivable balance.',
                ],
            ],
            $sourceSalesOrder ? ('SO#' . $sourceSalesOrder->so_number . ' - ' . $memo) : $memo
        );
    }

    public function recordRefundReceiptEntry(Receipt $receipt): ?JournalEntry
    {
        $receipt->loadMissing(['arInvoice.sales_order']);

        $amount = round((float) $receipt->amount_paid, 2);
        if ($amount <= 0) {
            return null;
        }

        $refundAccount = $this->ensureAccount('4110', 'sales_returns_allowances', 'Sales Returns And Allowances', 'revenue', 'contra_revenue');
        $cashAccount = $this->resolveCashAccountByPaymentMode($receipt->payment_mode);
        $sourceSalesOrder = optional(optional($receipt->arInvoice)->sales_order);
        $memo = 'Refund receipt ' . $receipt->receipt_number . ' recorded for sales return.';

        return $this->createEntry(
            $receipt,
            $receipt->receipt_date,
            'refund_receipt',
            $memo,
            [
                [
                    'account_id' => $refundAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Recognize refunded sales amount.',
                ],
                [
                    'account_id' => $cashAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Record cash refunded to customer.',
                ],
            ],
            $sourceSalesOrder ? ('SO#' . $sourceSalesOrder->so_number . ' - ' . $memo) : $memo
        );
    }

    public function reverseEntriesForSource(object $source, string $reason, $entryDate = null): array
    {
        $entryDate = $entryDate ?: now()->toDateString();

        if (!$this->hasJournalReversalColumns()) {
            return [];
        }

        $entries = JournalEntry::with('lines')
            ->where('source_type', $source::class)
            ->where('source_id', $source->id)
            ->whereNull('reversal_of_id')
            ->whereNull('reversed_at')
            ->get();

        return $entries
            ->map(fn (JournalEntry $entry) => $this->reverseEntry($entry, $reason, $entryDate))
            ->all();
    }

    public function recordSalesOrderUpdateEntries(SalesOrder $salesOrder): array
    {
        return $this->reverseEntriesForSource($salesOrder, 'Sales order updated. Previous accounting entry reversed.', $salesOrder->order_date);
    }

    public function recordSalesOrderCancellationEntries(SalesOrder $salesOrder): array
    {
        return $this->reverseEntriesForSource($salesOrder, 'Sales order cancelled. Original accounting entry reversed.', now()->toDateString());
    }

    public function recordSalesReturnEntries(SalesOrder $salesOrder, Collection $itemsToProcess, Collection $returnRequests, ?Receipt $sourceReceipt = null): array
    {
        $salesOrder->loadMissing(['items']);

        $paymentMode = strtolower((string) $salesOrder->payment_mode);
        $isCreditSale = in_array($paymentMode, ['credit', 'credit sales'], true);
        $refundAccount = $this->ensureAccount('4110', 'sales_returns_allowances', 'Sales Returns And Allowances', 'revenue', 'contra_revenue');
        $creditAccount = $isCreditSale
            ? $this->ensureAccount('1100', 'accounts_receivable', 'Accounts Receivable', 'asset', 'current_asset')
            : $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset');

        $refundAmount = round($itemsToProcess->sum(function ($item) use ($returnRequests) {
            $returnQuantity = (int) optional($returnRequests->get($item->id))->return_quantity ?: (int) $item->quantity;
            $effectiveQuantity = min($returnQuantity, (int) $item->quantity);
            $discount = (float) ($item->discount_per_unit ?? 0);

            return ($effectiveQuantity * (float) $item->price) - ($effectiveQuantity * $discount);
        }), 2);

        $entries = [];

        if ($refundAmount > 0) {
            $memo = 'Sales return approved for SO#' . $salesOrder->so_number . '. Revenue reversal entry.';
            $entries[] = $this->createEntry(
                $salesOrder,
                now()->toDateString(),
                'sales_return_revenue',
                $memo,
                [
                    [
                        'account_id' => $refundAccount->id,
                        'line_type' => 'debit',
                        'amount' => $refundAmount,
                        'description' => 'Reverse sales revenue for returned goods.',
                    ],
                    [
                        'account_id' => $creditAccount->id,
                        'line_type' => 'credit',
                        'amount' => $refundAmount,
                        'description' => $isCreditSale ? 'Reduce receivable for returned goods.' : 'Reduce cash for refunded sale.',
                    ],
                ],
                $memo
            );
        }

        $restockableItems = $itemsToProcess->filter(function ($item) use ($returnRequests) {
            return (string) (optional($returnRequests->get($item->id))->return_condition ?? 'restockable') === 'restockable';
        });

        $restockedCost = round($restockableItems->sum(function ($item) use ($returnRequests) {
            $returnQuantity = (int) optional($returnRequests->get($item->id))->return_quantity ?: (int) $item->quantity;
            $effectiveQuantity = min($returnQuantity, (int) $item->quantity);
            $inventoryStock = InventoryStocks::with('receivedItem')
                ->where('batch_code', $item->batch_code)
                ->first();

            return $effectiveQuantity * (float) optional($inventoryStock?->receivedItem)->unit_cost;
        }), 2);

        if ($restockedCost > 0) {
            $inventoryAccount = $this->ensureAccount('1200', 'rice_inventory', 'Rice Inventory', 'asset', 'inventory');
            $cogsAccount = $this->ensureAccount('5100', 'cost_of_goods_sold', 'Cost of Goods Sold', 'expense', 'cost_of_sales');
            $memo = 'Sales return approved for SO#' . $salesOrder->so_number . '. Inventory restoration entry.';

            $entries[] = $this->createEntry(
                $salesOrder,
                now()->toDateString(),
                'sales_return_inventory',
                $memo,
                [
                    [
                        'account_id' => $inventoryAccount->id,
                        'line_type' => 'debit',
                        'amount' => $restockedCost,
                        'description' => 'Restore inventory for returned goods.',
                    ],
                    [
                        'account_id' => $cogsAccount->id,
                        'line_type' => 'credit',
                        'amount' => $restockedCost,
                        'description' => 'Reverse cost of goods sold for returned stock.',
                    ],
                ],
                $memo
            );
        }

        if ($sourceReceipt && !empty($entries)) {
            $this->reverseEntriesForSource($sourceReceipt, 'Receipt voided due to sales return.', now()->toDateString());
        }

        return $entries;
    }

    public function recordExpenseReleaseEntry(Expense $expense): ?JournalEntry
    {
        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $expenseAccount = $this->resolveExpenseAccount($expense->expense_type);
        $cashAccount = $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset');

        return $this->createEntry(
            $expense,
            $expense->expense_date,
            'expense_release',
            'Expense release for ' . $expense->expense_type . '.',
            [
                [
                    'account_id' => $expenseAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Recognize released expense.',
                ],
                [
                    'account_id' => $cashAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Reduce cash for expense payment.',
                ],
            ],
            'Expense #' . $expense->id . ' - Released expense posting.'
        );
    }

    public function recordReceivedStockEntry(ReceivedStock $receivedStock): ?JournalEntry
    {
        $receivedStock->loadMissing(['items.purchaseOrderItem', 'purchaseOrder']);

        $amount = round((float) $receivedStock->items->sum('total_cost'), 2);
        if ($amount <= 0) {
            return null;
        }

        $inventoryAccount = $this->ensureAccount('1200', 'rice_inventory', 'Rice Inventory', 'asset', 'inventory');
        $paymentMode = trim((string) ($receivedStock->payment_mode ?? 'Credit'));
        $isCreditPurchase = strtolower($paymentMode) === 'credit';
        $amountPaid = $isCreditPurchase ? 0 : round((float) ($receivedStock->amount_paid ?? 0), 2);
        $cashPaid = min($amountPaid, $amount);
        $remainingPayable = round(max($amount - $cashPaid, 0), 2);
        $payableAccount = $this->ensureAccount('2000', 'accounts_payable', 'Accounts Payable', 'liability', 'current_liability');
        $cashAccount = $isCreditPurchase ? null : $this->resolveCashAccountByPaymentMode($paymentMode);
        $entryType = ($isCreditPurchase || $remainingPayable > 0) ? 'purchase_receipt' : 'purchase_receipt_cash';
        $purchaseOrder = $receivedStock->purchaseOrder;
        $memo = 'Received stock ' . $receivedStock->received_no . ' posted from supplier delivery via ' . $paymentMode . '.';
        if (strtolower($paymentMode) === 'bank transfer') {
            $bankDetails = array_filter([
                $receivedStock->bank_name ? 'Bank: ' . $receivedStock->bank_name : null,
                $receivedStock->reference_number ? 'Ref#: ' . $receivedStock->reference_number : null,
            ]);

            if (!empty($bankDetails)) {
                $memo .= ' ' . implode(', ', $bankDetails) . '.';
            }
        }
        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'line_type' => 'debit',
                'amount' => $amount,
                'description' => 'Increase inventory for received goods.',
            ],
        ];

        if ($cashPaid > 0 && $cashAccount) {
            $lines[] = [
                'account_id' => $cashAccount->id,
                'line_type' => 'credit',
                'amount' => $cashPaid,
                'description' => 'Record immediate payment for received goods via ' . strtolower($paymentMode) . '.',
            ];
        }

        if ($isCreditPurchase || $remainingPayable > 0) {
            $lines[] = [
                'account_id' => $payableAccount->id,
                'line_type' => 'credit',
                'amount' => $isCreditPurchase ? $amount : $remainingPayable,
                'description' => $isCreditPurchase
                    ? 'Recognize supplier payable for received goods.'
                    : 'Recognize remaining supplier payable after partial payment.',
            ];
        }

        return $this->createEntry(
            $receivedStock,
            $receivedStock->received_date,
            $entryType,
            $memo,
            $lines,
            $purchaseOrder ? ('PO#' . ($purchaseOrder->po_number ?: $purchaseOrder->pr_number) . ' - ' . $memo) : $memo
        );
    }

    public function recordReceivedStockPaymentEntry(ReceivedStock $receivedStock, ReceivedStockPayment $payment): ?JournalEntry
    {
        $amount = round((float) $payment->amount_paid, 2);
        if ($amount <= 0) {
            return null;
        }

        $receivedStock->loadMissing('purchaseOrder');

        $payableAccount = $this->ensureAccount('2000', 'accounts_payable', 'Accounts Payable', 'liability', 'current_liability');
        $cashAccount = $this->resolveCashAccountByPaymentMode($payment->payment_mode);
        $purchaseOrder = $receivedStock->purchaseOrder;
        $memo = 'Supplier payment recorded for received stock ' . $receivedStock->received_no . ' via ' . $payment->payment_mode . '.';

        if (strtolower((string) $payment->payment_mode) === 'bank transfer') {
            $bankDetails = array_filter([
                $payment->bank_name ? 'Bank: ' . $payment->bank_name : null,
                $payment->reference_number ? 'Ref#: ' . $payment->reference_number : null,
            ]);

            if (!empty($bankDetails)) {
                $memo .= ' ' . implode(', ', $bankDetails) . '.';
            }
        }

        return $this->createEntry(
            $payment,
            $payment->payment_date,
            'accounts_payable_payment',
            $memo,
            [
                [
                    'account_id' => $payableAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Reduce supplier payable balance.',
                ],
                [
                    'account_id' => $cashAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Record supplier payment via ' . strtolower((string) $payment->payment_mode) . '.',
                ],
            ],
            $purchaseOrder ? ('PO#' . ($purchaseOrder->po_number ?: $purchaseOrder->pr_number) . ' - ' . $memo) : $memo
        );
    }

    public function recordInventoryAdjustmentEntry(InventoryAdjustment $adjustment): ?JournalEntry
    {
        $adjustment->loadMissing(['inventoryStock.receivedItem']);

        $inventoryStock = $adjustment->inventoryStock;
        $unitCost = (float) optional($inventoryStock?->receivedItem)->unit_cost;
        if ($unitCost <= 0) {
            return null;
        }

        $previousQuantity = (float) $adjustment->previous_quantity;
        $newQuantity = (float) $adjustment->new_quantity;
        $difference = round($newQuantity - $previousQuantity, 4);

        if ($difference === 0.0) {
            return null;
        }

        $inventoryAccount = $this->ensureAccount('1200', 'rice_inventory', 'Rice Inventory', 'asset', 'inventory');
        $amount = round(abs($difference) * $unitCost, 2);
        if ($amount <= 0) {
            return null;
        }

        if ($difference > 0) {
            $offsetAccount = $this->ensureAccount('4200', 'inventory_adjustment_gain', 'Inventory Adjustment Gain', 'revenue', 'other_income');
            $lines = [
                [
                    'account_id' => $inventoryAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Increase inventory based on adjustment.',
                ],
                [
                    'account_id' => $offsetAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Record inventory adjustment gain.',
                ],
            ];
        } else {
            $offsetAccount = $this->ensureAccount('5200', 'inventory_adjustment_loss', 'Inventory Adjustment Loss', 'expense', 'inventory_variance');
            $lines = [
                [
                    'account_id' => $offsetAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Record inventory adjustment loss.',
                ],
                [
                    'account_id' => $inventoryAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Reduce inventory based on adjustment.',
                ],
            ];
        }

        return $this->createEntry(
            $adjustment,
            $adjustment->adjustment_date,
            'inventory_adjustment',
            'Inventory adjustment posted: ' . $adjustment->type,
            $lines,
            'Inventory Adjustment #' . $adjustment->id . ' - ' . ($adjustment->reason ?: 'Inventory adjustment')
        );
    }

    public function recordStockReturnDeliveryEntry(StockReturn $stockReturn): ?JournalEntry
    {
        $stockReturn->loadMissing(['items.purchaseOrderItem.product']);

        $amount = round($stockReturn->items->sum(function (StockReturnItem $item) {
            $unitCost = (float) optional($item->purchaseOrderItem)->unit_cost;

            return (int) $item->quantity * $unitCost;
        }), 2);

        if ($amount <= 0) {
            return null;
        }

        $payableAccount = $this->ensureAccount('2000', 'accounts_payable', 'Accounts Payable', 'liability', 'current_liability');
        $inventoryAccount = $this->ensureAccount('1200', 'rice_inventory', 'Inventory', 'asset', 'inventory');
        $memo = 'Return damaged stocks to supplier for Stock Return #' . ($stockReturn->stock_return_no ?: $stockReturn->id) . '.';

        return $this->createEntry(
            $stockReturn,
            now()->toDateString(),
            'stock_return',
            $memo,
            [
                [
                    'account_id' => $payableAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Return damaged stocks to supplier.',
                ],
                [
                    'account_id' => $inventoryAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Reduce inventory for goods returned to supplier.',
                ],
            ],
            'Stock Return #' . ($stockReturn->stock_return_no ?: $stockReturn->id) . ' - Return damaged stocks to supplier.'
        );
    }

    public function recordStockReturnLossEntry(StockReturn $stockReturn, StockReturnItem $item, int $lossQty): ?JournalEntry
    {
        $unitCost = (float) optional($item->purchaseOrderItem)->unit_cost;
        $amount = round($lossQty * $unitCost, 2);

        if ($lossQty <= 0 || $amount <= 0) {
            return null;
        }

        $lossAccount = $this->ensureAccount('5200', 'inventory_adjustment_loss', 'Inventory Adjustment Loss', 'expense', 'inventory_variance');
        $inventoryAccount = $this->ensureAccount('1200', 'rice_inventory', 'Inventory', 'asset', 'inventory');
        $memo = 'Stock adjustment - decrease due to damage/loss for Stock Return #' . ($stockReturn->stock_return_no ?: $stockReturn->id) . '.';

        return $this->createEntry(
            $stockReturn,
            now()->toDateString(),
            'stock_return_loss',
            $memo,
            [
                [
                    'account_id' => $lossAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Recognize inventory adjustment loss.',
                ],
                [
                    'account_id' => $inventoryAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Reduce inventory due to damage/loss.',
                ],
            ],
            'Stock Return #' . ($stockReturn->stock_return_no ?: $stockReturn->id) . ' - Stock adjustment decrease due to damage/loss.'
        );
    }

    public function recordStockReturnReplacementEntry(StockReturn $stockReturn, StockReturnItem $item, int $replacedQty): ?JournalEntry
    {
        $unitCost = (float) optional($item->purchaseOrderItem)->unit_cost;
        $amount = round($replacedQty * $unitCost, 2);

        if ($replacedQty <= 0 || $amount <= 0) {
            return null;
        }

        $inventoryAccount = $this->ensureAccount('1200', 'rice_inventory', 'Inventory', 'asset', 'inventory');
        $gainAccount = $this->ensureAccount('4200', 'inventory_adjustment_gain', 'Inventory Adjustment Gain', 'revenue', 'other_income');
        $memo = 'Stock adjustment - increase due to count correction for Stock Return #' . ($stockReturn->stock_return_no ?: $stockReturn->id) . '.';

        return $this->createEntry(
            $stockReturn,
            now()->toDateString(),
            'stock_return_replacement',
            $memo,
            [
                [
                    'account_id' => $inventoryAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Increase inventory for replacement received.',
                ],
                [
                    'account_id' => $gainAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Recognize inventory adjustment gain.',
                ],
            ],
            'Stock Return #' . ($stockReturn->stock_return_no ?: $stockReturn->id) . ' - Stock adjustment increase due to count correction.'
        );
    }

    private function createEntry(object $source, $entryDate, string $entryType, string $memo, array $lines, ?string $fullMemo = null, ?int $reversalOfId = null): JournalEntry
    {
        $entryDate = $entryDate ?: now()->toDateString();
        $journalNumber = JournalEntry::generateJournalNumber();
        $userId = auth()->id();

        $payload = [
            'journal_number' => $journalNumber,
            'description' => $memo,
            'entry_date' => $entryDate,
            'entry_type' => $entryType,
            'module_type' => $this->resolveModuleType($source),
            'ref_id' => $source->id,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'memo' => $fullMemo ?: $memo,
            'status' => $reversalOfId ? 'reversal_posted' : 'posted',
            'added_by_id' => $userId,
            'created_by_id' => $userId,
            'posted_at' => now(),
            'posted_by_id' => $userId,
        ];

        if ($this->hasJournalEntryColumn('journal_entry_code')) {
            $payload['journal_entry_code'] = $journalNumber;
        }

        if ($this->hasJournalReversalColumns()) {
            $payload['reversal_of_id'] = $reversalOfId;
        }

        $entry = JournalEntry::create($this->filterJournalEntryPayload($payload));

        foreach ($lines as $index => $line) {
            $entry->lines()->create([
                'account_id' => $line['account_id'],
                'account' => $line['account'] ?? optional(Account::find($line['account_id']))->name,
                'line_type' => $line['line_type'],
                'amount' => $line['amount'],
                'description' => $line['description'] ?? null,
                'line_order' => $index + 1,
            ]);
        }

        return $entry;
    }

    private function reverseEntry(JournalEntry $entry, string $reason, $entryDate): JournalEntry
    {
        $reversal = $this->createEntry(
            new class($entry) {
                public function __construct(public JournalEntry $entry)
                {
                    $this->id = $entry->source_id;
                }

                public int|string|null $id;
            },
            $entryDate,
            'reversal',
            'Reversal for ' . $entry->journal_number,
            $entry->lines->map(function ($line) {
                return [
                    'account_id' => $line->account_id,
                    'line_type' => $line->line_type === 'debit' ? 'credit' : 'debit',
                    'amount' => $line->amount,
                    'description' => 'Reversal of: ' . ($line->description ?? $line->line_type),
                ];
            })->all(),
            'Reversal of ' . $entry->journal_number . '. ' . $reason,
            $entry->id
        );

        $reversal->update([
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
        ]);

        if ($this->hasJournalReversalColumns()) {
            $entry->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);
        }

        return $reversal;
    }

    private function hasJournalReversalColumns(): bool
    {
        if (!Schema::hasTable('journal_entries')) {
            return false;
        }

        foreach (self::JOURNAL_REVERSAL_COLUMNS as $column) {
            if (!Schema::hasColumn('journal_entries', $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasJournalEntryColumn(string $column): bool
    {
        if (!Schema::hasTable('journal_entries')) {
            return false;
        }

        return in_array($column, $this->getJournalEntryColumns(), true);
    }

    private function getJournalEntryColumns(): array
    {
        if ($this->journalEntryColumns === null) {
            $this->journalEntryColumns = Schema::getColumnListing('journal_entries');
        }

        return $this->journalEntryColumns;
    }

    private function filterJournalEntryPayload(array $payload): array
    {
        $columns = $this->getJournalEntryColumns();

        return array_filter(
            $payload,
            fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function ensureAccount(string $code, string $slug, string $name, string $type, ?string $subtype = null): Account
    {
        return Account::firstOrCreate(
            ['slug' => $slug],
            [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'subtype' => $subtype,
                'is_active' => true,
            ]
        );
    }

    private function calculateCostOfGoodsSold(SalesOrder $salesOrder): float
    {
        $cost = 0;

        foreach ($salesOrder->items as $item) {
            $inventoryStock = InventoryStocks::with('receivedItem')
                ->where('batch_code', $item->batch_code)
                ->first();

            $unitCost = (float) optional($inventoryStock?->receivedItem)->unit_cost;
            $cost += $unitCost * (float) $item->quantity;
        }

        return $cost;
    }

    private function resolveExpenseAccount(?string $expenseType): Account
    {
        return match (strtolower((string) $expenseType)) {
            'utilities' => $this->ensureAccount('5300', 'utilities_expense', 'Utilities Expense', 'expense', 'operating_expense'),
            'supplies' => $this->ensureAccount('5310', 'supplies_expense', 'Supplies Expense', 'expense', 'operating_expense'),
            'transportation' => $this->ensureAccount('5320', 'transportation_expense', 'Transportation Expense', 'expense', 'operating_expense'),
            'maintenance' => $this->ensureAccount('5330', 'maintenance_expense', 'Maintenance Expense', 'expense', 'operating_expense'),
            'operational' => $this->ensureAccount('5340', 'operational_expense', 'Operational Expense', 'expense', 'operating_expense'),
            default => $this->ensureAccount('5390', 'other_expense', 'Other Expense', 'expense', 'operating_expense'),
        };
    }

    private function resolveCashAccountByPaymentMode(?string $paymentMode): Account
    {
        return match (strtolower((string) $paymentMode)) {
            'bank transfer' => $this->ensureAccount('1010', 'cash_in_bank', 'Cash In Bank', 'asset', 'current_asset'),
            'credit card', 'debit card' => $this->ensureAccount('1020', 'card_clearing', 'Card Clearing', 'asset', 'current_asset'),
            default => $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset'),
        };
    }

    private function resolveModuleType(object $source): string
    {
        return match (class_basename($source)) {
            'ReceivedStock', 'InventoryAdjustment', 'StockReturn', 'StockReturnItem' => 'inventory',
            'SalesOrder', 'Receipt' => 'sales',
            'Expense' => 'expenses',
            default => 'accounting',
        };
    }
}
