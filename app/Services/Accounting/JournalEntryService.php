<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\JournalEntry;
use App\Models\PettyCashTransaction;
use App\Models\Receipt;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\SalesOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class JournalEntryService
{
    private const JOURNAL_REVERSAL_COLUMNS = [
        'reversal_of_id',
        'reversed_at',
        'reversal_reason',
    ];

    public function recordSaleEntries(SalesOrder $salesOrder): array
    {
        $salesOrder->loadMissing(['items']);

        $revenueAmount = round((float) $salesOrder->total_amount, 2);
        $costAmount = round($this->calculateCostOfGoodsSold($salesOrder), 2);
        $paymentMode = strtolower((string) $salesOrder->payment_mode);
        $isCreditSale = in_array($paymentMode, ['credit', 'credit sales'], true);

        $cashOrReceivableAccount = $isCreditSale
            ? $this->ensureAccount('1100', 'accounts_receivable', 'Accounts Receivable', 'asset', 'current_asset')
            : $this->resolveCashAccountByPaymentMode($salesOrder->payment_mode, $salesOrder->bank_account_id ?? null);

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

        $cashAccount = $this->resolveCashAccountByPaymentMode($receipt->payment_mode, $receipt->bank_account_id ?? null);
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
                $salesOrder->approved_at?->toDateString() ?? now()->toDateString(),
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
                $salesOrder->approved_at?->toDateString() ?? now()->toDateString(),
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

        $damagedItems = $itemsToProcess->filter(function ($item) use ($returnRequests) {
            return (string) (optional($returnRequests->get($item->id))->return_condition ?? 'restockable') === 'damaged';
        });

        $damagedCost = round($damagedItems->sum(function ($item) use ($returnRequests) {
            $returnQuantity = (int) optional($returnRequests->get($item->id))->return_quantity ?: (int) $item->quantity;
            $effectiveQuantity = min($returnQuantity, (int) $item->quantity);
            $inventoryStock = InventoryStocks::with('receivedItem')
                ->where('batch_code', $item->batch_code)
                ->first();

            return $effectiveQuantity * (float) optional($inventoryStock?->receivedItem)->unit_cost;
        }), 2);

        if ($damagedCost > 0) {
            $lossAccount = $this->ensureAccount('5400', 'loss_on_damaged_goods', 'Loss on Damaged Goods', 'expense', 'operating_expense');
            $cogsAccount = $this->ensureAccount('5100', 'cost_of_goods_sold', 'Cost of Goods Sold', 'expense', 'cost_of_sales');
            $memo = 'Sales return approved for SO#' . $salesOrder->so_number . '. Damaged goods write-off entry.';

            $entries[] = $this->createEntry(
                $salesOrder,
                $salesOrder->approved_at?->toDateString() ?? now()->toDateString(),
                'sales_return_damage_writeoff',
                $memo,
                [
                    [
                        'account_id' => $lossAccount->id,
                        'line_type' => 'debit',
                        'amount' => $damagedCost,
                        'description' => 'Recognize loss on damaged returned goods.',
                    ],
                    [
                        'account_id' => $cogsAccount->id,
                        'line_type' => 'credit',
                        'amount' => $damagedCost,
                        'description' => 'Reverse cost of goods sold for damaged return.',
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

    public function recordReplenishmentEntry(\App\Models\ReplenishmentRequest $replenishment): ?JournalEntry
    {
        $expenses = $replenishment->expenses;
        $total    = round((float) $replenishment->total_amount, 2);

        if ($total <= 0 || $expenses->isEmpty()) {
            return null;
        }

        // Group expenses by type and build one debit line per category
        $lines = [];
        foreach ($expenses->groupBy('expense_type') as $type => $group) {
            $subtotal = round((float) $group->sum('amount'), 2);
            if ($subtotal <= 0) continue;
            $account = $this->resolveExpenseAccount($type);
            $lines[] = [
                'account_id'  => $account->id,
                'line_type'   => 'debit',
                'amount'      => $subtotal,
                'description' => ucfirst($type) . ' expenses — ' . $replenishment->reference_no,
            ];
        }

        // Credit: Cash in Bank (replenishment check drawn from bank to restore fund)
        $cashAccount = $this->ensureAccount('1011', 'cash_in_bank', 'Cash in Bank', 'asset', 'cash');
        $lines[] = [
            'account_id'  => $cashAccount->id,
            'line_type'   => 'credit',
            'amount'      => $total,
            'description' => 'Replenishment check issued — ' . $replenishment->reference_no,
        ];

        $memo = 'Petty cash replenishment approved. ' . $replenishment->reference_no
            . ' | Fund: ' . optional($replenishment->fund)->name
            . ' | ' . $expenses->count() . ' expense(s).';

        return $this->createEntry(
            $replenishment,
            $replenishment->reviewed_at?->toDateString() ?? now()->toDateString(),
            'petty_cash_replenishment',
            $memo,
            $lines,
            $memo
        );
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


    public function recordGeneralExpenseEntry(Expense $expense): ?JournalEntry
    {
        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $expenseAccount = $expense->gl_account_id
            ? Account::find($expense->gl_account_id) ?? $this->resolveExpenseAccount($expense->expense_type)
            : $this->resolveExpenseAccount($expense->expense_type);

        $creditAccount = $this->resolvePaymentAccount($expense->payment_method, $expense->bank_account_id);

        $payee = $expense->payee ? ' — ' . $expense->payee : '';
        $memo = ($expenseAccount->name ?? $expense->expense_type) . $payee
            . ($expense->reference_no ? ' [Ref: ' . $expense->reference_no . ']' : '');

        return $this->createEntry(
            $expense,
            $expense->expense_date,
            'general_expense',
            $memo,
            [
                [
                    'account_id'  => $expenseAccount->id,
                    'line_type'   => 'debit',
                    'amount'      => $amount,
                    'description' => 'Record ' . ($expenseAccount->name ?? 'expense') . '.',
                ],
                [
                    'account_id'  => $creditAccount->id,
                    'line_type'   => 'credit',
                    'amount'      => $amount,
                    'description' => 'Payment via ' . ($expense->payment_method ?? 'cash') . '.',
                ],
            ],
            'General Expense #' . $expense->id . ' released.'
        );
    }

    private function resolvePaymentAccount(?string $paymentMethod, ?int $bankAccountId): Account
    {
        if ($bankAccountId) {
            $bank = \App\Models\BankAccount::find($bankAccountId);
            if ($bank) {
                $slug = \Illuminate\Support\Str::slug($bank->account_name);
                return $this->ensureAccount(
                    $bank->gl_code,
                    $slug,
                    $bank->bank_name . ' — ' . $bank->account_name,
                    'asset',
                    'current_asset'
                );
            }
        }

        return match (strtolower((string) $paymentMethod)) {
            'bank_transfer', 'check' => $this->ensureAccount('1011', 'cash_in_bank', 'Cash in Bank', 'asset', 'cash'),
            default                  => $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset'),
        };
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
        $cashAccount = $isCreditPurchase ? null : $this->resolveCashAccountByPaymentMode($paymentMode, $receivedStock->bank_account_id ?? null);
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
        $cashAccount = $this->resolveCashAccountByPaymentMode($payment->payment_mode, $payment->bank_account_id ?? null);
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

    public function recordFundTransferEntry(FundTransfer $transfer): JournalEntry
    {
        $transfer->loadMissing(['fromBankAccount', 'toBankAccount']);

        $fromAccount = $this->resolveBankAccountGl($transfer->fromBankAccount);
        $toAccount   = $this->resolveBankAccountGl($transfer->toBankAccount);
        $amount      = round((float) $transfer->amount, 2);

        return $this->createEntry(
            $transfer,
            $transfer->transfer_date,
            'fund_transfer',
            'Fund transfer ' . $transfer->transfer_no . ' from ' . $transfer->fromBankAccount->bank_name . ' to ' . $transfer->toBankAccount->bank_name . '.',
            [
                ['account_id' => $toAccount->id,   'line_type' => 'debit',  'amount' => $amount, 'description' => 'Receive funds from ' . $transfer->fromBankAccount->account_name . '.'],
                ['account_id' => $fromAccount->id, 'line_type' => 'credit', 'amount' => $amount, 'description' => 'Transfer funds to ' . $transfer->toBankAccount->account_name . '.'],
            ]
        );
    }

    public function recordPettyCashReplenishment(PettyCashTransaction $txn): JournalEntry
    {
        $txn->loadMissing(['fund', 'bankAccount']);

        $pettyCashAccount = $this->resolvePettyCashAccount($txn->fund);
        $sourceAccount    = $txn->source_type === 'bank' && $txn->bankAccount
            ? $this->resolveBankAccountGl($txn->bankAccount)
            : $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset');

        $amount = round((float) $txn->amount, 2);

        return $this->createEntry(
            $txn,
            $txn->transaction_date,
            'petty_cash_replenishment',
            'Petty cash replenishment ' . $txn->transaction_no . ' — ' . $txn->fund->name . '.',
            [
                ['account_id' => $pettyCashAccount->id, 'line_type' => 'debit',  'amount' => $amount, 'description' => 'Top up petty cash fund.'],
                ['account_id' => $sourceAccount->id,    'line_type' => 'credit', 'amount' => $amount, 'description' => 'Fund source withdrawal.'],
            ]
        );
    }

    public function recordFundTopUp(\App\Models\PettyCashFund $fund, float $amount, string $date, ?int $bankAccountId = null, ?string $notes = null): JournalEntry
    {
        $pettyCashAccount = $this->resolvePettyCashAccount($fund);
        $creditAccount    = $bankAccountId
            ? $this->resolvePaymentAccount('bank_transfer', $bankAccountId)
            : $this->ensureAccount('1011', 'cash_in_bank', 'Cash in Bank', 'asset', 'cash');

        return $this->createEntry(
            $fund,
            $date,
            'petty_cash_top_up',
            'Top-up of petty cash fund: ' . $fund->name . ($notes ? ' — ' . $notes : '') . '.',
            [
                ['account_id' => $pettyCashAccount->id, 'line_type' => 'debit',  'amount' => $amount, 'description' => 'Increase petty cash fund: ' . $fund->name . '.'],
                ['account_id' => $creditAccount->id,    'line_type' => 'credit', 'amount' => $amount, 'description' => 'Transfer from bank to petty cash fund.'],
            ]
        );
    }

    public function recordFundCapitalization(\App\Models\PettyCashFund $fund, float $amount): JournalEntry
    {
        $pettyCashAccount = $this->resolvePettyCashAccount($fund);
        $cashAccount      = $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset');

        return $this->createEntry(
            $fund,
            now()->toDateString(),
            'petty_cash_capitalization',
            'Initial capitalization of petty cash fund: ' . $fund->name . '.',
            [
                ['account_id' => $pettyCashAccount->id, 'line_type' => 'debit',  'amount' => $amount, 'description' => 'Establish petty cash fund: ' . $fund->name . '.'],
                ['account_id' => $cashAccount->id,      'line_type' => 'credit', 'amount' => $amount, 'description' => 'Transfer from cash on hand to petty cash fund.'],
            ]
        );
    }

    public function recordBankDepositEntry(BankDeposit $deposit): JournalEntry
    {
        $deposit->loadMissing(['cashAccount', 'bankAccount']);

        $bankGlAccount  = $this->resolveBankAccountGl($deposit->bankAccount);
        $cashGlAccount  = $deposit->cashAccount;
        $amount         = round((float) $deposit->amount, 2);

        return $this->createEntry(
            $deposit,
            $deposit->deposit_date,
            'bank_deposit',
            'Bank deposit ' . $deposit->deposit_no . ' — cash deposited to ' . $deposit->bankAccount->bank_name . '.',
            [
                ['account_id' => $bankGlAccount->id,  'line_type' => 'debit',  'amount' => $amount, 'description' => 'Deposit cash to ' . $deposit->bankAccount->bank_name . ' — ' . $deposit->bankAccount->account_name . '.'],
                ['account_id' => $cashGlAccount->id,  'line_type' => 'credit', 'amount' => $amount, 'description' => 'Reduce cash on hand account: ' . $deposit->cashAccount->name . '.'],
            ]
        );
    }

    public function recordPayrollEntry(\App\Models\Payroll $payroll): array
    {
        $payroll->loadMissing(['items']);

        $grossPay      = round((float) $payroll->items->sum('total_earnings'), 2);
        $totalDeductions = round((float) $payroll->items->sum('total_deductions'), 2);
        $netPay        = round((float) $payroll->items->sum('net_salary'), 2);

        if ($netPay <= 0) {
            return [];
        }

        $salaryExpenseAccount   = $this->ensureAccount('5200', 'salaries_wages_expense', 'Salaries and Wages Expense', 'expense', 'operating_expense');
        $cashAccount            = $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset');
        $payrollDate            = $payroll->pay_period_end ?? now()->toDateString();
        $memo                   = 'Payroll #' . $payroll->payroll_no . ' released for period ' . $payroll->pay_period_start . ' to ' . $payroll->pay_period_end . '.';

        $entries = [];

        if ($grossPay === $netPay || $totalDeductions <= 0) {
            // No deductions — single entry
            $entries[] = $this->createEntry(
                $payroll,
                $payrollDate,
                'payroll_release',
                $memo,
                [
                    ['account_id' => $salaryExpenseAccount->id, 'line_type' => 'debit',  'amount' => $netPay, 'description' => 'Record salary and wages expense.'],
                    ['account_id' => $cashAccount->id,          'line_type' => 'credit', 'amount' => $netPay, 'description' => 'Reduce cash for payroll disbursement.'],
                ]
            );
        } else {
            // Gross expense debit; net cash credit; deductions payable credit
            $deductionsPayableAccount = $this->ensureAccount('2100', 'payroll_deductions_payable', 'Payroll Deductions Payable', 'liability', 'current_liability');
            $entries[] = $this->createEntry(
                $payroll,
                $payrollDate,
                'payroll_release',
                $memo,
                [
                    ['account_id' => $salaryExpenseAccount->id,   'line_type' => 'debit',  'amount' => $grossPay,        'description' => 'Record gross salary and wages expense.'],
                    ['account_id' => $cashAccount->id,            'line_type' => 'credit', 'amount' => $netPay,          'description' => 'Reduce cash for net payroll disbursement.'],
                    ['account_id' => $deductionsPayableAccount->id,'line_type' => 'credit', 'amount' => $totalDeductions, 'description' => 'Record payroll deductions payable (loans, withholdings).'],
                ]
            );
        }

        return $entries;
    }

    public function recordPettyCashDisbursement(PettyCashTransaction $txn): JournalEntry
    {
        $txn->loadMissing(['fund']);

        $pettyCashAccount = $this->resolvePettyCashAccount($txn->fund);
        $expenseAccount   = $this->resolveExpenseAccount($txn->category);
        $amount           = round((float) $txn->amount, 2);

        return $this->createEntry(
            $txn,
            $txn->transaction_date,
            'petty_cash_disbursement',
            'Petty cash disbursement ' . $txn->transaction_no . ($txn->description ? ' — ' . $txn->description : '') . '.',
            [
                ['account_id' => $expenseAccount->id,   'line_type' => 'debit',  'amount' => $amount, 'description' => 'Record petty cash expense: ' . ($txn->description ?? $txn->category ?? 'disbursement') . '.'],
                ['account_id' => $pettyCashAccount->id, 'line_type' => 'credit', 'amount' => $amount, 'description' => 'Reduce petty cash fund.'],
            ]
        );
    }

    private function resolveBankAccountGl(BankAccount $bankAccount): Account
    {
        return $this->ensureAccount(
            $bankAccount->gl_code,
            \Illuminate\Support\Str::slug($bankAccount->account_name),
            $bankAccount->bank_name . ' — ' . $bankAccount->account_name,
            'asset',
            'current_asset'
        );
    }

    private function resolvePettyCashAccount(\App\Models\PettyCashFund $fund): Account
    {
        return $this->ensureAccount(
            $fund->gl_code,
            'petty_cash_' . \Illuminate\Support\Str::slug($fund->name),
            'Petty Cash — ' . $fund->name,
            'asset',
            'current_asset'
        );
    }

    private function createEntry(object $source, $entryDate, string $entryType, string $memo, array $lines, ?string $fullMemo = null, ?int $reversalOfId = null): JournalEntry
    {
        $entryDate = $entryDate ?: now()->toDateString();

        $payload = [
            'journal_number' => JournalEntry::generateJournalNumber(),
            'entry_date' => $entryDate,
            'entry_type' => $entryType,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'memo' => $fullMemo ?: $memo,
            'status' => $reversalOfId ? 'reversal_posted' : 'posted',
            'created_by_id' => auth()->id(),
            'posted_at' => now(),
        ];

        if ($this->hasJournalReversalColumns()) {
            $payload['reversal_of_id'] = $reversalOfId;
        }

        $entry = JournalEntry::create($payload);

        foreach ($lines as $index => $line) {
            $entry->lines()->create([
                'account_id' => $line['account_id'],
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

    private function ensureAccount(string $code, string $slug, string $name, string $type, ?string $subtype = null): Account
    {
        $account = Account::where('slug', $slug)->first()
            ?? Account::where('code', $code)->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'code'      => $code,
            'slug'      => $slug,
            'name'      => $name,
            'type'      => $type,
            'subtype'   => $subtype,
            'is_active' => true,
        ]);
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
            'utilities'      => $this->ensureAccount('5300', 'utilities_expense',     'Utilities Expense',     'expense', 'operating_expense'),
            'supplies'       => $this->ensureAccount('5311', 'supplies_expense',       'Supplies Expense',       'expense', 'operating_expense'),
            'transportation' => $this->ensureAccount('5320', 'transportation_expense', 'Transportation Expense', 'expense', 'operating_expense'),
            'maintenance'    => $this->ensureAccount('5341', 'maintenance_expense',    'Maintenance Expense',    'expense', 'operating_expense'),
            'operational'    => $this->ensureAccount('5370', 'operational_expense',    'Operational Expense',    'expense', 'operating_expense'),
            default          => $this->ensureAccount('5390', 'other_expense',          'Other Expense',          'expense', 'operating_expense'),
        };
    }

    private function resolveCashAccountByPaymentMode(?string $paymentMode, ?int $bankAccountId = null): Account
    {
        if ($bankAccountId && strtolower((string) $paymentMode) === 'bank transfer') {
            $bankAccount = BankAccount::find($bankAccountId);
            if ($bankAccount) {
                $slug = \Illuminate\Support\Str::slug($bankAccount->account_name);
                return $this->ensureAccount(
                    $bankAccount->gl_code,
                    $slug,
                    $bankAccount->bank_name . ' — ' . $bankAccount->account_name,
                    'asset',
                    'current_asset'
                );
            }
        }

        return match (strtolower((string) $paymentMode)) {
            'bank transfer' => $this->ensureAccount('1010', 'cash_in_bank', 'Cash In Bank', 'asset', 'current_asset'),
            'credit card', 'debit card' => $this->ensureAccount('1020', 'card_clearing', 'Card Clearing', 'asset', 'current_asset'),
            default => $this->ensureAccount('1000', 'cash', 'Cash', 'asset', 'current_asset'),
        };
    }
}
