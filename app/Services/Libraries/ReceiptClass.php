<?php

namespace App\Services\Libraries;


use App\Models\Receipt;
use App\Models\ArInvoice;
use App\Models\SalesOrder;
use App\Models\ListStatus;
use App\Models\SalesOrderIncentive;
use App\Http\Resources\Libraries\ReceiptResource;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class ReceiptClass
{
    public function __construct(protected JournalEntryService $journalEntryService)
    {
    }

    public function lists($request){
        return ReceiptResource::collection(
            Receipt::with(['arInvoice.sales_order.customer', 'status', 'sourceReceipt'])
                ->where(function ($query) {
                    $query->whereNull('receipt_type')
                        ->orWhere('receipt_type', '!=', 'refund');
                })
                ->when($request->location_id, function ($query, $locationId) {
                    $query->whereHas('arInvoice.sales_order', function ($q) use ($locationId) {
                        $q->where('location_id', $locationId);
                    });
                })
                ->when($request->keyword, function($query) use ($request) {
                    $query->whereHas('arInvoice.sales_order.customer', function($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->keyword . '%');
                    })->orWhere('receipt_number', 'like', '%' . $request->keyword . '%');
                })
                ->when($request->status, function ($query, $status) {
                    $query->whereHas('status', function ($q) use ($status) {
                        $q->where('slug', $status);
                    });
                    // Receipts already inside a remittance must never appear in the pending picker
                    if ($status === 'pending') {
                        $query->whereNull('remittance_id');
                    }
                })
                // Scope to the logged-in user's own receipts (used by Prepare Remittance modal)
                ->when($request->scope_to_rep, function ($query) {
                    $employee = Auth::user()?->employee;
                    if ($employee) {
                        $query->whereHas('arInvoice.sales_order', function ($q) use ($employee) {
                            $q->where('sales_rep_id', $employee->id);
                        });
                    }
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->count ?? 10)
        );
    }

    public function dashboard(){
        $cancelledId = ListStatus::getBySlug('cancelled')?->id ?? 0;

        return [
            'total_receipts' => Receipt::where('status_id', '!=', $cancelledId)
                ->where(function ($q) {
                    $q->whereNull('receipt_type')->orWhere('receipt_type', '!=', 'refund');
                })->count(),
            'total_amount_collected' => Receipt::where('status_id', '!=', $cancelledId)
                ->where(function ($q) {
                    $q->whereNull('receipt_type')->orWhereNotIn('receipt_type', ['refund', 'updated']);
                })->sum('amount_paid'),
        ];
    }

    // Bug 19 fix: wrap in DB::transaction so all writes are atomic
    public function save($request){
        return DB::transaction(function () use ($request) {
            $arInvoice = ArInvoice::with('sales_order')->findOrFail($request->ar_invoice_id);

            if ($request->amount_paid <= 0) {
                throw ValidationException::withMessages(['amount_paid' => 'Payment amount must be greater than zero.']);
            }

            if ($request->amount_paid > $arInvoice->balance_due) {
                throw ValidationException::withMessages(['amount_paid' => 'Payment of ₱' . number_format($request->amount_paid, 2) . ' exceeds the remaining balance of ₱' . number_format($arInvoice->balance_due, 2) . '.']);
            }

            // Compute updated balance before creating the receipt so we can store it
            $newBalanceDue = $arInvoice->balance_due - $request->amount_paid;

            // Bug 4 fix: include balance_due on the receipt record
            $receipt = Receipt::create([
                'ar_invoice_id'  => $request->ar_invoice_id,
                'status_id'      => ListStatus::getBySlug('pending')?->id,
                'receipt_number' => Receipt::generateReceiptNumber(),
                'receipt_type'   => 'payment',
                'receipt_date'   => $request->receipt_date,
                'amount_paid'    => $request->amount_paid,
                'balance_due'    => max(0, $newBalanceDue),
                'payment_mode'   => $request->payment_mode,
                'customer_id'    => optional($arInvoice->sales_order)->customer_id,
            ]);

            $arInvoice->amount_paid += $request->amount_paid;
            $arInvoice->balance_due  = $newBalanceDue;

            if ($arInvoice->balance_due <= 0) {
                $arInvoice->status_id = ListStatus::getBySlug('paid')?->id;
            } elseif ($arInvoice->amount_paid > 0) {
                $arInvoice->status_id = ListStatus::getBySlug('partially-paid')?->id;
            }

            $arInvoice->save();

            $salesOrder = $arInvoice->sales_order;
            if ($salesOrder) {
                if ($arInvoice->balance_due <= 0) {
                    $salesOrder->update(['status_id' => ListStatus::getBySlug('closed')?->id]);

                    if (!SalesOrderIncentive::where('sales_order_id', $salesOrder->id)->exists()) {
                        $sold_quantity    = $salesOrder->items->sum('quantity');
                        $product_total_kg = $salesOrder->items->sum(fn($item) => ($item->product->weight ?? 0) * $item->quantity);

                        SalesOrderIncentive::create([
                            'sales_order_id'   => $salesOrder->id,
                            'employee_id'      => $salesOrder->sales_rep_id,
                            'sold_quantity'    => $sold_quantity,
                            'product_total_kg' => $product_total_kg,
                            'amount'           => $product_total_kg / 25,
                            'payroll_id'       => null,
                        ]);
                    }
                } else {
                    $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')?->id]);
                }
            }

            $this->journalEntryService->recordReceiptEntry($receipt);

            return [
                'data'    => $receipt,
                'message' => 'Receipt saved successfully!',
                'info'    => "You've successfully saved the receipt",
            ];
        });
    }

    public function update($request){
        $receipt = Receipt::findOrFail($request->id);
        $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt updated. Previous receipt entry reversed.', $request->receipt_date);
        $oldAmount = $receipt->amount_paid;

        $receipt->update($request->only([
            'ar_invoice_id', 'receipt_date', 'amount_paid', 'payment_mode'
        ]));

        $arInvoice = ArInvoice::with('sales_order')->find($receipt->ar_invoice_id);
        $arInvoice->amount_paid = $arInvoice->amount_paid - $oldAmount + $request->amount_paid;
        $arInvoice->balance_due = $arInvoice->balance_due + $oldAmount - $request->amount_paid;

        if ($arInvoice->balance_due <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('paid')?->id;
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')?->id;
        } else {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')?->id;
        }

        $arInvoice->save();

        $receipt->update(['balance_due' => max(0, $arInvoice->balance_due)]);

        $salesOrder = $arInvoice->sales_order;
        if ($salesOrder) {
            if ($arInvoice->balance_due <= 0) {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('closed')?->id]);
            } elseif ($arInvoice->amount_paid > 0) {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')?->id]);
            } else {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('for-payment')?->id]);
            }
        }

        $this->journalEntryService->recordReceiptEntry($receipt->fresh());

        return [
            'data'    => $receipt,
            'message' => 'Receipt updated successfully!',
            'info'    => "You've successfully updated the receipt",
        ];
    }

    public function delete($id){
        $receipt = Receipt::with('status')->findOrFail($id);

        // Bug 6 fix: block deletion if receipt is inside a remittance
        if (!is_null($receipt->remittance_id)) {
            throw ValidationException::withMessages([
                'receipt' => 'This receipt belongs to a remittance and cannot be deleted directly. Remove it from the remittance first.',
            ]);
        }

        $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt deleted. Original collection entry reversed.', now()->toDateString());
        $arInvoice = ArInvoice::with('sales_order')->findOrFail($receipt->ar_invoice_id);

        $arInvoice->amount_paid -= $receipt->amount_paid;
        $arInvoice->balance_due += $receipt->amount_paid;

        if ($arInvoice->amount_paid <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')?->id;
        } else {
            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')?->id;
        }

        $arInvoice->save();

        // Bug 5 fix: sync SO status after reversing the payment
        $salesOrder = $arInvoice->sales_order;
        if ($salesOrder) {
            if ($arInvoice->amount_paid <= 0) {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('for-payment')?->id]);
            } else {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')?->id]);
            }
        }

        $receipt->delete();

        return [
            'data' => null,
            'message' => 'Receipt deleted successfully!',
            'info' => "You've successfully deleted the receipt",
        ];
    }

    public function show($id){
        return Receipt::with(['arInvoice.sales_order.customer', 'arInvoice.sales_order.items.product', 'status'])->findOrFail($id);
    }


}
