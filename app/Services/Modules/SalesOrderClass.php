<?php

namespace App\Services\Modules;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\ArInvoice;
use App\Models\Receipt;
use App\Models\InventoryStocks;
use App\Models\ReceivedItem;
use App\Models\Product;
use App\Models\ListStatus;
use App\Models\InventoryAdjustment;
use App\Models\SalesReturnHistory;
use App\Models\AppSetting;
use App\Http\Resources\Modules\SalesOrderResource;
use App\Services\Accounting\JournalEntryService;
use App\Services\Modules\InventoryService;


class SalesOrderClass
{
    public static function returnWindowDays(): int
    {
        return (int) AppSetting::get('return_grace_period', env('SALES_RETURN_WINDOW_DAYS', 7));
    }

    protected $inventoryService;
    protected $journalEntryService;

    public function __construct(InventoryService $inventoryService, JournalEntryService $journalEntryService)
    {
        $this->inventoryService = $inventoryService;
        $this->journalEntryService = $journalEntryService;
    }

    public function lists($request){
        $returnStatuses = ['sales-returned', 'sales-return-approval', 'partially-returned'];
        $requestedStatuses = is_array($request->status) ? $request->status : [$request->status];
        $requestedStatuses = array_values(array_filter($requestedStatuses));
        $includesReturnStatuses = count(array_intersect($requestedStatuses, $returnStatuses)) > 0;

        $query = SalesOrder::with([
            'items.salesReturnItems',
            'items.product',
            'arInvoices.receipts.status',
            'customer',
            'status',
            'sub_status',
            'created_by',
            'approved_by',
            'location',
            'salesRep',
            'driver',
            'returnReplacements.product',
        ])
            ->when($request->keyword, function ($query,$keyword) {
                $query->where('so_number', 'LIKE', "%{$keyword}%")
                      ->orWhereHas('status', function($q) use ($keyword){
                          $q->where('name', 'LIKE', "%{$keyword}%");
                      })
                      ->orWhereHas('customer', function($q) use ($keyword){
                          $q->where('name', 'LIKE', "%{$keyword}%");
                      });
            })
            ->when($request->status, function ($query, $status) {
                $statuses = is_array($status) ? $status : [$status];
                $statuses = array_values(array_filter($statuses));

                $query->whereHas('status', function($q) use ($statuses){
                    $q->whereIn('slug', $statuses);
                });
            })
            ->when(!$includesReturnStatuses, function ($query) use ($returnStatuses) {
                $query->whereDoesntHave('status', function ($q) use ($returnStatuses) {
                    $q->whereIn('slug', $returnStatuses);
                });
            })
            ->when($request->sub_status, function ($query, $sub_status) {
                // Bug 3 fix: was 'subStatus', relationship is named 'sub_status'
                $query->whereHas('sub_status', function($q) use ($sub_status){
                    $q->where('slug', $sub_status);
                });
            })
            ->when($request->location_id, function ($query, $location_id) {
                $query->where('location_id', $location_id);
            })
            ->when($request->status_id, function ($query, $status_id) {
                $query->where('status_id', $status_id);
            });

        $data = SalesOrderResource::collection(
            $query->orderBy('created_at', 'DESC')
                  ->paginate($request->count)
        );
        return $data;
    }


    public function save($request){

        // Validate stock availability for all items
        foreach($request->items as $item){
            if (!$this->inventoryService->hasSufficientStock($item['product_id'], $item['quantity'], $item['batch_code'])) {
                $product = Product::find($item['product_id']);
                throw ValidationException::withMessages(['stock' => 'Insufficient stock for product: ' . ($product ? $product->name : 'Unknown Product') . ' in batch ' . $item['batch_code']]);
            }
        }

        // Enforce credit limit for credit sales
        $isCreditMode = in_array(strtolower((string) $request->payment_mode), ['credit', 'credit sales'], true);
        if ($isCreditMode) {
            $customer = \App\Models\Customer::find($request->customer_id);
            if ($customer && $customer->credit_limit > 0) {
                $orderTotal = collect($request->items)->sum(function ($item) {
                    return ($item['price'] * $item['quantity']) - (($item['discount_per_unit'] ?? 0) * $item['quantity']);
                });
                $outstanding = ArInvoice::whereHas('sales_order', fn($q) => $q->where('customer_id', $request->customer_id))
                    ->where('balance_due', '>', 0)
                    ->sum('balance_due');
                if (($outstanding + $orderTotal) > $customer->credit_limit) {
                    throw ValidationException::withMessages([
                        'credit_limit' => sprintf(
                            'Credit limit exceeded. Limit: ₱%s | Outstanding: ₱%s | This order: ₱%s',
                            number_format($customer->credit_limit, 2),
                            number_format($outstanding, 2),
                            number_format($orderTotal, 2)
                        ),
                    ]);
                }
            }
        }

        $externalLocationIds = \App\Models\ListLocation::where('name', '!=', 'Zamboanga City')->pluck('id');
        $isExternal = in_array($request->location_id, $externalLocationIds->toArray());
        $prefix = $isExternal ? 'SO-EXT' : 'SO';
        $data = null;
        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $candidate = new SalesOrder();
            $candidate->so_number = SalesOrder::generateSoNumber(null, $prefix);
            $candidate->order_date = $request->order_date;
            $candidate->customer_id = $request->customer_id;
            $candidate->sales_rep_id = $request->sales_rep_id;
            $candidate->driver_id = $request->driver_id;
            $candidate->payment_mode = $request->payment_mode;
            $candidate->due_date = in_array(strtolower((string) $request->payment_mode), ['credit', 'credit sales'], true) ? $request->due_date : null;
            $candidate->location_id = $request->location_id;
            $candidate->added_by_id = auth()->user()->id;
            $candidate->status_id = ListStatus::getBySlug('for-payment')?->id;

            try {
                $candidate->save();
                $data = $candidate;
                break;
            } catch (QueryException $e) {
                $isDuplicate = ((string) $e->getCode() === '23000')
                    && str_contains(strtolower($e->getMessage()), 'sales_orders_so_number_unique');
                if (!$isDuplicate || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        $totalAmount = 0;
        $totalDiscount = 0;

        foreach($request->items as $item){
            $price = $item['price'];
            $discount_per_unit = $item['discount_per_unit'] ?? 0;
            $quantity = $item['quantity'];
            $total_discount_amount = $discount_per_unit * $quantity;

            $data->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'price' => $price,
                'price_type' => $item['price_type'],
                'batch_code' => $item['batch_code'],
                'discount_per_unit' => $discount_per_unit,
            ]);

            $totalAmount += ($price * $quantity) - $total_discount_amount;
            $totalDiscount += $total_discount_amount;

            $this->inventoryService->deductStock($item['product_id'], $item['quantity'], 'Sale - SO#' . $data->so_number, $item['batch_code']);
        }

        $data->update([
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
        ]);

        $isCreditSale = in_array(strtolower((string) $data->payment_mode), ['credit', 'credit sales'], true);

        $invoice = new ArInvoice();
        $invoice->sales_order_id = $data->id;
        $invoice->invoice_number = ArInvoice::generateInvoiceNumber();
        $invoice->invoice_date = $data->order_date;
        $invoice->amount_due   = $totalAmount;
        $invoice->amount_paid  = $isCreditSale ? 0 : $totalAmount;
        $invoice->balance_due  = $isCreditSale ? $totalAmount : 0;
        $invoice->total_discount = $data->total_discount;
        $invoice->status_id = $isCreditSale
            ? ListStatus::getBySlug('unpaid')?->id
            : ListStatus::getBySlug('paid')?->id;
        $invoice->due_date = $isCreditSale ? $data->due_date : null;
        $invoice->save();

        $autoReceiptId = null;
        if (!$isCreditSale) {
            $autoReceipt = Receipt::create([
                'ar_invoice_id'  => $invoice->id,
                'customer_id'    => $data->customer_id,
                'status_id'      => ListStatus::getBySlug('pending')?->id,
                'receipt_number' => Receipt::generateReceiptNumber(),
                'receipt_type'   => 'payment',
                'receipt_date'   => $data->order_date,
                'amount_paid'    => $totalAmount,
                'balance_due'    => 0,
                'payment_mode'   => $data->payment_mode,
            ]);

            $autoReceiptId = $autoReceipt->id;
            $data->update(['status_id' => ListStatus::getBySlug('closed')?->id]);
        }

        $this->journalEntryService->recordSaleEntries($data->load('items'));

        $data = SalesOrder::with(['items', 'customer', 'status', 'created_by', 'arInvoices'])->find($data->id);

        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order saved successfully!',
            'info' => "You've successfully saved the Sales Order",
            'receipt_id' => $autoReceiptId,
        ];
    }


    public function update($request){
        if (empty($request->items)) {
            throw ValidationException::withMessages(['items' => 'A sales order must have at least one item.']);
        }

        $data = SalesOrder::findOrFail($request->id);
        $data->load(['items', 'arInvoices']);

        $this->journalEntryService->reverseEntriesForSource($data, 'Sales order updated. Previous accounting entry reversed.', $request->order_date);

        foreach ($data->items as $item) {
            $this->inventoryService->addStock($item->product_id, $item->quantity, 'Update SO - Restore Old Stock - SO#' . $data->so_number, $item->batch_code);
        }

        $data->update([
            'customer_id' => $request->customer_id,
            'order_date' => $request->order_date,
            'sales_rep_id' => $request->sales_rep_id,
            'driver_id' => $request->driver_id,
            'payment_mode' => $request->payment_mode,
            'due_date' => in_array(strtolower((string) $request->payment_mode), ['credit', 'credit sales'], true) ? $request->due_date : null,
            'location_id' => $request->location_id,
            'updated_by_id' => auth()->user()->id,
        ]);

        $data->items()->delete();

        foreach($request->items as $item){
            if (!$this->inventoryService->hasSufficientStock($item['product_id'], $item['quantity'], $item['batch_code'])) {
                $product = Product::find($item['product_id']);
                throw ValidationException::withMessages(['stock' => 'Insufficient stock for product: ' . ($product ? $product->name : 'Unknown Product') . ' in batch ' . $item['batch_code']]);
            }
        }

        $totalAmount = 0;
        $totalDiscount = 0;
        foreach($request->items as $item){
            $price = $item['price'];
            $discount_per_unit = $item['discount_per_unit'] ?? 0;
            $quantity = $item['quantity'];
            $total_discount_amount = $discount_per_unit * $quantity;

            $data->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'price' => $price,
                'price_type' => $item['price_type'],
                'batch_code' => $item['batch_code'],
                'discount_per_unit' => $discount_per_unit,
            ]);

            $totalAmount += ($price * $quantity) - $total_discount_amount;
            $totalDiscount += $total_discount_amount;

            $this->inventoryService->deductStock($item['product_id'], $item['quantity'], 'Update Sale - SO#' . $data->so_number, $item['batch_code']);
        }

        $data->update([
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
        ]);

        $invoice = $data->arInvoices()->first();
        $isCreditSaleNow = in_array(strtolower((string) $data->payment_mode), ['credit', 'credit sales'], true);

        if ($invoice) {
            // Bug 10 fix: sync invoice status and amount_paid/balance_due with new payment mode
            $invoice->update([
                'amount_due'     => $totalAmount,
                'balance_due'    => $isCreditSaleNow ? $totalAmount : 0,
                'amount_paid'    => $isCreditSaleNow ? 0 : $totalAmount,
                'total_discount' => $totalDiscount,
                'status_id'      => $isCreditSaleNow
                    ? ListStatus::getBySlug('unpaid')?->id
                    : ListStatus::getBySlug('paid')?->id,
            ]);
        }

        // Bug 10 fix: sync SO status with the new payment mode
        $data->update([
            'status_id' => $isCreditSaleNow
                ? ListStatus::getBySlug('for-payment')?->id
                : ListStatus::getBySlug('closed')?->id,
        ]);

        // Sync the auto-receipt when cash SO total changes; create one if Credit→Cash conversion
        if (!$isCreditSaleNow && $invoice) {
            $paymentReceipt = Receipt::where('ar_invoice_id', $invoice->id)
                ->where('receipt_type', 'payment')
                ->whereHas('status', fn($q) => $q->where('slug', 'pending'))
                ->first();
            if ($paymentReceipt) {
                $paymentReceipt->update([
                    'amount_paid' => $totalAmount,
                    'balance_due' => 0,
                ]);
            } else {
                // Credit → Cash: no receipt existed yet — create one now
                $paymentReceipt = Receipt::create([
                    'ar_invoice_id'  => $invoice->id,
                    'customer_id'    => $data->customer_id,
                    'status_id'      => ListStatus::getBySlug('pending')?->id,
                    'receipt_number' => Receipt::generateReceiptNumber(),
                    'receipt_type'   => 'payment',
                    'receipt_date'   => $data->order_date,
                    'amount_paid'    => $totalAmount,
                    'balance_due'    => 0,
                    'payment_mode'   => $data->payment_mode,
                ]);
                $this->journalEntryService->recordReceiptEntry($paymentReceipt);
            }
        }

        $this->journalEntryService->recordSaleEntries($data->fresh(['items']));

        $data = SalesOrder::with(['items', 'customer', 'status', 'updated_by', 'arInvoices'])->find($data->id);

        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order updated successfully!',
            'info' => "You've successfully updated the Sales Order"
        ];
    }

    public function approve($id, $itemIds = [], $replacementItems = []){
        $data = SalesOrder::with('items')->findOrFail($id);

        $currentStatus = $data->status ? $data->status->slug : '';

        if ($currentStatus === 'sales-return-approval') {
            $returnRequests = DB::table('sales_return_items')
                ->join('sales_order_items', 'sales_order_items.id', '=', 'sales_return_items.sales_order_item_id')
                ->where('sales_order_items.sales_order_id', $id)
                ->get([
                    'sales_return_items.sales_order_item_id',
                    'sales_return_items.source_receipt_id',
                    'sales_return_items.return_quantity',
                    'sales_return_items.return_condition',
                ])
                ->keyBy('sales_order_item_id');
            $sourceReceiptId = (int) optional($returnRequests->first())->source_receipt_id;
            $sourceReceipt = $sourceReceiptId ? Receipt::find($sourceReceiptId) : null;

            $itemsToProcess = $data->items;

            if (!empty($itemIds)) {
                $itemsToProcess = $data->items->whereIn('id', $itemIds);
            }

            // Guard: only process items that were actually submitted for return
            $itemsToProcess = $itemsToProcess->filter(fn($item) => $returnRequests->has($item->id));

            foreach ($itemsToProcess as $item) {
                $returnRequest = $returnRequests->get($item->id);
                $returnQuantity = (int) ($returnRequest->return_quantity ?? $item->quantity);
                $returnCondition = (string) ($returnRequest->return_condition ?? 'restockable');
                if ($returnQuantity <= 0) {
                    continue;
                }

                $remainingReturnable = (int) $item->quantity - (int) $item->returned_quantity;
                $effectiveQuantity = min($returnQuantity, $remainingReturnable);

                if ($returnCondition === 'restockable') {
                    $this->inventoryService->addStock(
                        $item->product_id,
                        $effectiveQuantity,
                        'Sales Return Approved - Restockable - SO#' . $data->so_number,
                        $item->batch_code
                    );
                    continue;
                }

                $lossType = $returnCondition === 'damaged' ? 'damage' : 'loss';
                $this->inventoryService->recordLossOrDamage(
                    $item->product_id,
                    $effectiveQuantity,
                    'Sales Return ' . strtoupper(str_replace('_', ' ', $returnCondition)) . ' - SO#' . $data->so_number,
                    $item->batch_code,
                    $lossType
                );
            }

            // Persist returned quantities so second returns can't re-return the same items.
            // Capture effectiveQuantities BEFORE increment() mutates returned_quantity on the model.
            $effectiveQuantities = [];
            foreach ($itemsToProcess as $item) {
                $returnRequest = $returnRequests->get($item->id);
                $returnQuantity = (int) ($returnRequest->return_quantity ?? $item->quantity);
                $remainingReturnable = (int) $item->quantity - (int) $item->returned_quantity;
                $effectiveQuantity = min($returnQuantity, $remainingReturnable);
                if ($effectiveQuantity > 0) {
                    $effectiveQuantities[$item->id] = $effectiveQuantity;
                    $item->increment('returned_quantity', $effectiveQuantity);
                }
            }

            // Full return only when admin approved ALL items AND every submitted quantity covers the full order qty
            $approvedItemIds = collect($itemIds)->map(fn($i) => (int) $i);
            $allItemsFullyReturned = !empty($itemIds) && $data->items->every(
                function ($item) use ($returnRequests, $approvedItemIds) {
                    $requestedQuantity = (int) optional($returnRequests->get($item->id))->return_quantity;
                    return $approvedItemIds->contains((int) $item->id)
                        && $requestedQuantity >= (int) $item->quantity;
                }
            );
            $isFullReturn = !empty($itemIds) && $allItemsFullyReturned;
            $refundAmount = $itemsToProcess->sum(function($item) use ($returnRequests) {
                $returnQuantity = (int) optional($returnRequests->get($item->id))->return_quantity ?: (int) $item->quantity;
                $effectivePrice = max(0, (float) $item->price - (float) $item->discount_per_unit);
                return min($returnQuantity, (int) $item->quantity) * $effectivePrice;
            });
            $isCashSale = !in_array(strtolower((string) $data->payment_mode), ['credit', 'credit sales'], true);
            $refundReceipt = null;
            $updatedReceipt = null;
            $extraReceipt = null;

            if ($isFullReturn) {
                $data->update([
                    'status_id'      => ListStatus::getBySlug('sales-returned')?->id,
                    'approved_by_id' => auth()->user()->id,
                    'approved_at'    => now(),
                ]);

                if ($isCashSale) {
                    // Cash: swap items — no refund, process replacements and charge any difference
                    $extraReceipt = $this->processReplacementItems($data, $replacementItems, $refundAmount);
                } else {
                    // Credit: cancel all invoices and receipts, then add replacement to AR if any
                    $arInvoices = ArInvoice::where('sales_order_id', $id)->get();
                    foreach ($arInvoices as $invoice) {
                        foreach (Receipt::where('ar_invoice_id', $invoice->id)->get() as $receipt) {
                            $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt voided because of full sales return.', now()->toDateString());
                            $receipt->update(['status_id' => ListStatus::getBySlug('cancelled')?->id]);
                        }
                        $invoice->update(['status_id' => ListStatus::getBySlug('cancelled')?->id]);
                    }
                    // Reset the first invoice's amounts so processReplacementItems only adds the extra charge.
                    $firstInvoice = $arInvoices->first();
                    if ($firstInvoice) {
                        $firstInvoice->update(['amount_due' => 0, 'amount_paid' => 0, 'balance_due' => 0]);
                    }
                    if ($sourceReceipt) {
                        $refundReceipt = $this->createRefundReceipt($data, $sourceReceipt, $refundAmount);
                    }
                    $extraReceipt = $this->processReplacementItems($data, $replacementItems, $refundAmount);
                }

                $this->logReturnHistory($data, $itemsToProcess, $returnRequests, $effectiveQuantities);
                if ($sourceReceipt) {
                    $this->writeReturnNoteToReceipt($sourceReceipt, $itemsToProcess, $returnRequests, $replacementItems, $extraReceipt);
                }

                // Bug 12 fix: delete ALL sales_return_items for this order
                DB::table('sales_return_items')
                    ->whereIn('sales_order_item_id', $data->items->pluck('id'))
                    ->delete();

                $this->journalEntryService->recordSalesReturnEntries($data, $itemsToProcess, $returnRequests, $sourceReceipt);
                if (!empty($replacementItems)) {
                    $this->journalEntryService->recordReplacementEntries($data, $replacementItems, $extraReceipt);
                }

                return [
                    'data'       => SalesOrder::find($id),
                    'message'    => 'Sales Order return approved successfully!',
                    'info'       => "Return approved. Replacement items have been issued.",
                    'receipt_id' => $extraReceipt?->id ?? $refundReceipt?->id,
                ];
            } else {
                $data->update([
                    'status_id'      => ListStatus::getBySlug('partially-returned')?->id,
                    'approved_by_id' => auth()->user()->id,
                    'approved_at'    => now(),
                ]);

                if ($isCashSale) {
                    // Cash: swap items — no refund, process replacements and charge any difference
                    $extraReceipt = $this->processReplacementItems($data, $replacementItems, $refundAmount);
                } else {
                    // Credit: reduce AR balance by return value, then add replacement value back
                    $arInvoice = $data->arInvoices()->first();
                    if ($arInvoice) {
                        $newAmountDue  = max(0, (float) $arInvoice->amount_due  - $refundAmount);
                        $newAmountPaid = max(0, (float) $arInvoice->amount_paid - $refundAmount);
                        $arInvoice->amount_due  = $newAmountDue;
                        $arInvoice->amount_paid = $newAmountPaid;
                        $arInvoice->balance_due = max(0, $newAmountDue - $newAmountPaid);
                        if ($arInvoice->balance_due <= 0) {
                            $arInvoice->status_id = ListStatus::getBySlug('paid')?->id;
                        } elseif ($newAmountPaid > 0) {
                            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')?->id;
                        } else {
                            $arInvoice->status_id = ListStatus::getBySlug('unpaid')?->id;
                        }
                        $arInvoice->save();
                    }
                    if ($sourceReceipt) {
                        $sourceReceipt->load('status');
                        $originalStatusSlug = $sourceReceipt->status?->slug ?? 'pending';
                        $this->journalEntryService->reverseEntriesForSource($sourceReceipt, 'Receipt voided because of partial sales return.', now()->toDateString());
                        $sourceReceipt->update(['status_id' => ListStatus::getBySlug('cancelled')?->id]);
                        $refundReceipt  = $this->createRefundReceipt($data, $sourceReceipt, $refundAmount);
                        $updatedReceipt = $this->createUpdatedReceipt($data, $refundReceipt, $originalStatusSlug);
                    }
                    // Add replacement items to AR invoice (customer owes the replacement value)
                    $extraReceipt = $this->processReplacementItems($data, $replacementItems, $refundAmount);
                }

                $this->logReturnHistory($data, $itemsToProcess, $returnRequests, $effectiveQuantities);
                if ($sourceReceipt) {
                    $this->writeReturnNoteToReceipt($sourceReceipt, $itemsToProcess, $returnRequests, $replacementItems, $extraReceipt);
                }

                // Bug 13 fix: delete ALL sales_return_items for this order after partial return approval
                DB::table('sales_return_items')
                    ->whereIn('sales_order_item_id', $data->items->pluck('id'))
                    ->delete();

                $this->journalEntryService->recordSalesReturnEntries($data, $itemsToProcess, $returnRequests, $sourceReceipt);
                if ($updatedReceipt) {
                    $this->journalEntryService->recordReceiptEntry($updatedReceipt);
                }
                if (!empty($replacementItems)) {
                    $this->journalEntryService->recordReplacementEntries($data, $replacementItems, $extraReceipt);
                }

                return [
                    'data'       => SalesOrder::find($id),
                    'message'    => 'Partial Sales Order return approved successfully!',
                    'info'       => "Return approved. Replacement items have been issued.",
                    'receipt_id' => $extraReceipt?->id ?? $updatedReceipt?->id,
                ];
            }
        } else {
            if (in_array($currentStatus, ['sales-returned', 'partially-returned'], true)) {
                throw ValidationException::withMessages([
                    'approve' => 'This sales order has already been returned and cannot be re-approved.',
                ]);
            }

            $data->update([
                'status_id' => ListStatus::getBySlug('approved')?->id,
                'approved_by_id' => auth()->user()->id,
                'approved_at' => now(),
            ]);

            return [
                'data' => SalesOrder::find($id),
                'message' => 'Sales Order approved successfully!',
                'info' => "You've successfully approved the Sales Order"
            ];
        }
    }

    public function cancel($id){
        $data = SalesOrder::findOrFail($id);
        $data->load(['items', 'arInvoices.receipts.status', 'status']);
        $cancelledStatusId = ListStatus::getBySlug('cancelled')?->id;

        if (optional($data->status)->slug === 'cancelled') {
            return [
                'data' => $data,
                'message' => 'Sales Order already cancelled.',
                'info' => 'This Sales Order has already been voided.',
            ];
        }

        // Bug 18 fix: block only when non-cancelled receipts exist, not just amount_paid > 0
        $hasActiveReceipts = $data->arInvoices->contains(function ($inv) {
            return $inv->receipts->contains(fn($r) => optional($r->status)->slug !== 'cancelled');
        });
        if ($hasActiveReceipts) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cancel' => ['This Sales Order has active receipts. Void or refund all receipts before cancelling.'],
            ]);
        }

        foreach($data->items as $item){
            $this->inventoryService->addStock($item->product_id, $item->quantity, 'Cancel SO - Restore Stock - SO#' . $data->so_number, $item->batch_code);
        }

        $this->journalEntryService->recordSalesOrderCancellationEntries($data);

        foreach ($data->arInvoices as $invoice) {
            foreach ($invoice->receipts as $receipt) {
                $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt reversed because related sales order was cancelled.', now()->toDateString());
                $receipt->update([
                    'status_id' => $cancelledStatusId,
                ]);
            }

            $invoice->update([
                'status_id' => $cancelledStatusId,
            ]);
        }

        $data->update([
            'status_id' => $cancelledStatusId,
        ]);

        return [
            'data' => SalesOrder::find($id),
            'message' => 'Sales Order cancelled successfully!',
            'info' => "You've successfully cancelled the Sales Order"
        ];
    }

    public function dashboard(){
        $total_sales_orders = SalesOrder::count();
        $today_orders = SalesOrder::whereDate('created_at', today())->count();
        $total_revenue = SalesOrder::where('status_id', '!=', ListStatus::getBySlug('cancelled')?->id ?? 0)->sum('total_amount') ?? 0;
        $pending_orders = SalesOrder::where('status_id', ListStatus::getBySlug('pending')?->id ?? 0)->count();
        $cancelled_orders = SalesOrder::where('status_id', ListStatus::getBySlug('cancelled')?->id ?? 0)->count();

        return [
            'total_sales_orders' => $total_sales_orders,
            'today_orders' => $today_orders,
            'total_revenue' => $total_revenue,
            'pending_orders' => $pending_orders,
            'cancelled_orders' => $cancelled_orders,
        ];
    }

    public function stockAvailability(){
        $products = Product::with(['brand', 'unit'])->get();

        $stockData = [];
        $totalKgLeft = 0;
        $fiveKgSacks = 0;
        $tenKgSacks = 0;
        $twentyFiveKgSacks = 0;

        foreach($products as $product){
            $currentStock = $this->inventoryService->getCurrentStock($product->id);

            if($currentStock > 0){
                $totalKg = $currentStock * $product->weight;

                $stockData[] = [
                    'product_name' => $product->name,
                    'brand_name' => $product->brand ? $product->brand->name : 'No Brand',
                    'weight' => $product->weight,
                    'unit' => $product->unit ? $product->unit->name : 'No Unit',
                    'total_quantity' => $currentStock,
                    'total_kg' => $totalKg
                ];

                $totalKgLeft += $totalKg;

                if($product->weight == 5){
                    $fiveKgSacks += $currentStock;
                } elseif($product->weight == 10){
                    $tenKgSacks += $currentStock;
                } elseif($product->weight == 25){
                    $twentyFiveKgSacks += $currentStock;
                }
            }
        }

        return [
            'total_kg_left' => $totalKgLeft,
            'five_kg_sacks_left' => $fiveKgSacks,
            'ten_kg_sacks_left' => $tenKgSacks,
            'twenty_five_kg_sacks_left' => $twentyFiveKgSacks,
            'products' => $stockData
        ];
    }

    public function adjustment($request){
        $sales_order = SalesOrder::findOrFail($request->id);

        $normalizedType = Str::of((string) $request->type)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->value();

        $status = null;
        if ($normalizedType === 'sales return') {
            $status = ListStatus::getBySlug('sales-return-approval');
        } elseif ($normalizedType === 'sales allowance') {
            $status = ListStatus::getBySlug('allowance-applied');
        }

        if (!$status) {
            return [
                'data' => $sales_order,
                'message' => 'Invalid adjustment type!',
                'info' => 'Please specify a valid adjustment type (Sales Return or Sales Allowance)',
                'status' => 'error',
            ];
        }

        if ($normalizedType === 'sales return') {
            $this->validateSalesReturnEligibility($sales_order, $request->receipt_id);

            $selectedItemIds = collect($request->item_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();
            $returnQuantities = collect($request->return_quantities ?? [])
                ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty]);
            $returnConditions = collect($request->return_conditions ?? [])
                ->mapWithKeys(fn ($condition, $itemId) => [(int) $itemId => (string) $condition]);
            $sourceReceiptId = $request->receipt_id ? (int) $request->receipt_id : null;

            $orderItemIds = $sales_order->items()->pluck('id');
            $orderItems = $sales_order->items()->get()->keyBy('id');

            DB::table('sales_return_items')
                ->whereIn('sales_order_item_id', $orderItemIds)
                ->delete();

            if ($selectedItemIds->isNotEmpty()) {
                $rows = $selectedItemIds
                    ->intersect($orderItemIds)
                    ->map(function ($itemId) use ($orderItems, $returnQuantities, $returnConditions, $sourceReceiptId) {
                        $orderItem = $orderItems->get($itemId);
                        if (!$orderItem) {
                            return null;
                        }

                        $alreadyReturned = (int) $orderItem->returned_quantity;
                        $remainingReturnable = (int) $orderItem->quantity - $alreadyReturned;
                        if ($remainingReturnable <= 0) {
                            throw ValidationException::withMessages([
                                'return_quantities' => "Item #{$itemId} has already been fully returned.",
                            ]);
                        }
                        $requestedQuantity = (int) ($returnQuantities->get($itemId) ?? $remainingReturnable);
                        if ($requestedQuantity < 1 || $requestedQuantity > $remainingReturnable) {
                            throw ValidationException::withMessages([
                                'return_quantities' => "Return quantity for item #{$itemId} must be between 1 and {$remainingReturnable} (already returned: {$alreadyReturned}).",
                            ]);
                        }

                        $returnCondition = $returnConditions->get($itemId, 'restockable');
                        if (!in_array($returnCondition, ['restockable', 'damaged'], true)) {
                            throw ValidationException::withMessages([
                                'return_conditions' => "Return condition for item #{$itemId} is invalid.",
                            ]);
                        }

                        return [
                            'sales_order_item_id' => $itemId,
                            'source_receipt_id' => $sourceReceiptId,
                            'return_quantity' => $requestedQuantity,
                            'return_condition' => $returnCondition,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($rows)) {
                    DB::table('sales_return_items')->insert($rows);
                }
            }
        }

        $sales_order->update([ 'status_id' => $status->id]);


        return [
            'data' => $sales_order,
            'message' => 'Sales adjustment applied successfully!',
            'info' => "You've successfully applied the sales adjustment"
        ];
    }

    private function validateSalesReturnEligibility(SalesOrder $salesOrder, $receiptId = null): void
    {
        $receipt = null;

        if ($receiptId) {
            $receipt = Receipt::with('status', 'arInvoice.sales_order')->find($receiptId);

            if (!$receipt) {
                throw ValidationException::withMessages([
                    'receipt_id' => 'Selected receipt does not exist.',
                ]);
            }

            $receiptSalesOrderId = optional(optional($receipt->arInvoice)->sales_order)->id;
            if ((int) $receiptSalesOrderId !== (int) $salesOrder->id) {
                throw ValidationException::withMessages([
                    'receipt_id' => 'Selected receipt does not belong to this sales order.',
                ]);
            }
        } else {
            $receipt = Receipt::with('status', 'arInvoice.sales_order')
                ->whereHas('arInvoice', function ($query) use ($salesOrder) {
                    $query->where('sales_order_id', $salesOrder->id);
                })
                ->orderByDesc('receipt_date')
                ->first();
        }

        if (!$receipt || !$receipt->receipt_date) {
            throw ValidationException::withMessages([
                'receipt_id' => 'A valid receipt with a receipt date is required for sales returns.',
            ]);
        }

        $salesOrderStatus = optional($salesOrder->status)->slug;
        if (in_array($salesOrderStatus, ['sales-returned', 'sales-return-approval', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'receipt_id' => 'This sales order is not eligible for return.',
            ]);
        }

        if (optional($receipt->status)->slug === 'cancelled') {
            throw ValidationException::withMessages([
                'receipt_id' => 'Cancelled receipts are not eligible for return.',
            ]);
        }

        $daysSinceReceipt = Carbon::parse($receipt->receipt_date)
            ->startOfDay()
            ->diffInDays(now()->startOfDay(), false);

        if ($daysSinceReceipt < 0 || $daysSinceReceipt > self::returnWindowDays()) {
            throw ValidationException::withMessages([
                'receipt_id' => 'This receipt is outside the allowed 7-day return window.',
            ]);
        }
    }

    private function createRefundReceipt(SalesOrder $salesOrder, ?Receipt $sourceReceipt, float $refundAmount): ?Receipt
    {
        if ($refundAmount <= 0) {
            return null;
        }

        $invoice = $salesOrder->arInvoices()->first();
        if (!$invoice) {
            return null;
        }

        return Receipt::create([
            'ar_invoice_id' => $invoice->id,
            'customer_id' => $salesOrder->customer_id,
            'status_id' => ListStatus::getBySlug('paid')?->id,
            'receipt_number' => Receipt::generateReceiptNumber(),
            'receipt_type' => 'refund',
            'source_receipt_id' => $sourceReceipt?->id,
            'receipt_date' => now()->toDateString(),
            'amount_paid' => $refundAmount,
            'balance_due' => $invoice->balance_due ?? 0,
            'payment_mode' => $sourceReceipt?->payment_mode ?? $salesOrder->payment_mode,
        ]);
    }

    private function processReplacementItems(SalesOrder $salesOrder, array $replacementItems, float $returnValue): ?Receipt
    {
        if (empty($replacementItems)) {
            return null;
        }

        $replacementTotal = 0;
        foreach ($replacementItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity  = (int) ($item['quantity']   ?? 0);
            $price     = (float) ($item['price']    ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $replacementTotal += $quantity * $price;
        }

        if (round($replacementTotal, 2) < round($returnValue, 2)) {
            throw ValidationException::withMessages([
                'replacement' => 'Replacement value (' . number_format($replacementTotal, 2) . ') must be equal to or greater than the return value (' . number_format($returnValue, 2) . '). No under-value replacements are allowed.',
            ]);
        }

        foreach ($replacementItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity  = (int) ($item['quantity']   ?? 0);
            $price     = (float) ($item['price']    ?? 0);
            if ($productId <= 0 || $quantity <= 0) continue;
            $this->inventoryService->deductStock(
                $productId,
                $quantity,
                'Sales Return Replacement - SO#' . $salesOrder->so_number,
            );
            \App\Models\SalesReturnReplacement::create([
                'sales_order_id' => $salesOrder->id,
                'product_id'     => $productId,
                'quantity'       => $quantity,
                'price'          => $price,
                'total_value'    => $quantity * $price,
                'replaced_at'    => now()->toDateString(),
                'replaced_by_id' => auth()->id(),
            ]);
        }

        $extraAmount = max(0, round($replacementTotal - $returnValue, 2));
        if ($extraAmount <= 0) {
            return null;
        }

        $arInvoice = $salesOrder->arInvoices()->first();
        if (!$arInvoice) {
            return null;
        }

        $arInvoice->amount_due  = (float) $arInvoice->amount_due  + $extraAmount;
        $arInvoice->balance_due = (float) $arInvoice->balance_due + $extraAmount;
        $arInvoice->status_id   = ListStatus::getBySlug('partially-paid')?->id;
        $arInvoice->save();

        return Receipt::create([
            'ar_invoice_id'  => $arInvoice->id,
            'customer_id'    => $salesOrder->customer_id,
            'status_id'      => ListStatus::getBySlug('pending')?->id,
            'receipt_number' => Receipt::generateReceiptNumber(),
            'receipt_type'   => 'payment',
            'receipt_date'   => now()->toDateString(),
            'amount_paid'    => $extraAmount,
            'balance_due'    => $extraAmount,
            'payment_mode'   => $salesOrder->payment_mode,
        ]);
    }

    private function writeReturnNoteToReceipt(Receipt $receipt, $itemsToProcess, $returnRequests, array $replacementItems, ?Receipt $extraReceipt = null): void
    {
        $returnedLines = [];
        foreach ($itemsToProcess as $item) {
            $returnRequest  = $returnRequests->get($item->id);
            $returnQuantity = (int) ($returnRequest->return_quantity ?? $item->quantity);
            $product        = Product::find($item->product_id);
            $productName    = $product?->name ?? "Product #{$item->product_id}";
            $returnedLines[] = "{$productName} ×{$returnQuantity}";
        }

        $replacementLines = [];
        $replacementTotal = 0;
        foreach ($replacementItems as $rep) {
            $productId  = (int) ($rep['product_id'] ?? 0);
            $quantity   = (int) ($rep['quantity']   ?? 0);
            $price      = (float) ($rep['price']    ?? 0);
            if ($productId <= 0 || $quantity <= 0) continue;
            $product    = Product::find($productId);
            $name       = $product?->name ?? "Product #{$productId}";
            $replacementLines[] = "{$name} ×{$quantity}";
            $replacementTotal += $quantity * $price;
        }

        $date     = now()->format('Y-m-d H:i');
        $returned = implode(', ', $returnedLines);
        $newNote  = "[{$date}] Return adjustment — Returned: {$returned}.";

        if (!empty($replacementLines)) {
            $replaced = implode(', ', $replacementLines);
            $newNote .= " Replaced with: {$replaced}.";
        }

        if ($extraReceipt) {
            $extra = number_format((float) $extraReceipt->amount_paid, 2);
            $newNote .= " Extra charged: ₱{$extra} (Receipt #{$extraReceipt->receipt_number}).";

            $extraNote = "[{$date}] Additional charge — replacement exceeded return value by ₱{$extra}. Ref: original receipt #{$receipt->receipt_number}.";
            $extraReceipt->update(['notes' => $extraNote]);
        }

        $existing = $receipt->notes ? $receipt->notes . "\n" : '';
        $receipt->update(['notes' => $existing . $newNote]);
    }

    private function logReturnHistory(SalesOrder $salesOrder, $itemsToProcess, $returnRequests, array $effectiveQuantities = []): void
    {
        foreach ($itemsToProcess as $item) {
            // Use pre-computed quantity to avoid stale post-increment value on the model.
            $effectiveQuantity = $effectiveQuantities[$item->id] ?? 0;
            if ($effectiveQuantity <= 0) {
                continue;
            }
            $returnRequest = $returnRequests->get($item->id);
            $unitPrice = max(0, (float) $item->price - (float) $item->discount_per_unit);
            SalesReturnHistory::create([
                'sales_order_id'      => $salesOrder->id,
                'sales_order_item_id' => $item->id,
                'product_id'          => $item->product_id,
                'quantity'            => $effectiveQuantity,
                'condition'           => (string) ($returnRequest->return_condition ?? 'restockable'),
                'unit_price'          => $unitPrice,
                'total_value'         => $effectiveQuantity * $unitPrice,
                'returned_at'         => now()->toDateString(),
                'approved_by_id'      => auth()->id(),
            ]);
        }
    }

    public function returnHistory($request): array
    {
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $to   = $request->to   ? Carbon::parse($request->to)->endOfDay()   : Carbon::now()->endOfMonth()->endOfDay();

        $rows = SalesReturnHistory::with(['product', 'salesOrder.customer', 'approvedBy'])
            ->whereBetween('returned_at', [$from->toDateString(), $to->toDateString()])
            ->when($request->condition, fn($q, $c) => $q->where('condition', $c))
            ->orderByDesc('returned_at')
            ->get();

        $summary = [
            'restockable' => ['qty' => 0, 'value' => 0],
            'damaged'     => ['qty' => 0, 'value' => 0],
            'loss'        => ['qty' => 0, 'value' => 0],
        ];

        foreach ($rows as $row) {
            $cond = $row->condition;
            if (!isset($summary[$cond])) {
                $summary[$cond] = ['qty' => 0, 'value' => 0];
            }
            $summary[$cond]['qty']   += $row->quantity;
            $summary[$cond]['value'] += (float) $row->total_value;
        }

        return [
            'rows'    => $rows->map(fn($r) => [
                'id'           => $r->id,
                'so_number'    => $r->salesOrder?->so_number,
                'customer'     => $r->salesOrder?->customer?->name,
                'product'      => $r->product?->name,
                'quantity'     => $r->quantity,
                'condition'    => $r->condition,
                'unit_price'   => $r->unit_price,
                'total_value'  => $r->total_value,
                'returned_at'  => $r->returned_at?->format('Y-m-d'),
                'approved_by'  => $r->approvedBy?->name,
            ]),
            'summary' => $summary,
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
        ];
    }

    private function createUpdatedReceipt(SalesOrder $salesOrder, ?Receipt $refundReceipt, string $originalStatusSlug = 'pending'): ?Receipt
    {
        if (!$refundReceipt) {
            return null;
        }

        $invoice = $salesOrder->arInvoices()->first();
        if (!$invoice) {
            return null;
        }

        // Bug 15 fix: guard against null sourceReceipt before accessing ->amount_paid
        $originalReceipt = $refundReceipt->sourceReceipt;
        if (!$originalReceipt) {
            return null;
        }

        $adjustedAmountPaid = max(0, (float) ($originalReceipt->amount_paid - $refundReceipt->amount_paid));

        // Inherit the original receipt's status — if it was already liquidated/remitted,
        // the adjusted receipt needs no remittance either.
        $inheritedStatus = in_array($originalStatusSlug, ['liquidated', 'paid']) ? 'liquidated' : 'pending';

        return Receipt::create([
            'ar_invoice_id' => $invoice->id,
            'customer_id' => $salesOrder->customer_id,
            'status_id' => ListStatus::getBySlug($inheritedStatus)?->id,
            'receipt_number' => Receipt::generateReceiptNumber(),
            'receipt_type' => 'updated',
            'source_receipt_id' => $refundReceipt->id,
            'receipt_date' => now()->toDateString(),
            'amount_paid' => $adjustedAmountPaid,
            'balance_due' => $invoice->balance_due ?? 0,
            'payment_mode' => $originalReceipt->payment_mode ?? $salesOrder->payment_mode ?? 'Updated Receipt',
        ]);
    }
}
