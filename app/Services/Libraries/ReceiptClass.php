<?php

namespace App\Services\Libraries;


use App\Models\Receipt;
use App\Models\ArInvoice;
use App\Models\SalesOrder;
use App\Models\ListStatus;
use App\Models\SalesOrderIncentive;
use App\Http\Resources\Libraries\ReceiptResource;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\DB;


class ReceiptClass
{
    public function __construct(protected JournalEntryService $journalEntryService)
    {
    }

    public function lists($request){
        return ReceiptResource::collection(
            Receipt::with(['arInvoice.sales_order.customer', 'arInvoice.sales_order.items.product', 'status', 'sourceReceipt'])
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
                })
                ->when($request->remittance_type, function ($query, $remittanceType) {
                    $type = strtolower((string) $remittanceType);
                    $query->whereHas('arInvoice.sales_order', function ($q) use ($type) {
                        $creditSalesModes = ['credit', 'credit sales'];
                        if ($type === 'credit') {
                            $q->whereIn(DB::raw('LOWER(payment_mode)'), $creditSalesModes);
                            return;
                        }

                        $q->where(function ($inner) use ($creditSalesModes) {
                            $inner->whereNotIn(DB::raw('LOWER(payment_mode)'), $creditSalesModes)
                                ->orWhereNull('payment_mode');
                        });
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->count ?? 10)
        );
    }

    public function dashboard(){
        $cancelledId = ListStatus::getBySlug('cancelled')->id;

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

    public function save($request){
        $arInvoice = ArInvoice::with('sales_order')->findOrFail($request->ar_invoice_id);

        $receipt = Receipt::create([
            'ar_invoice_id' => $request->ar_invoice_id,
            'status_id'     => ListStatus::getBySlug('paid')->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'receipt_type'  => 'payment',
            'receipt_date'  => $request->receipt_date,
            'amount_paid'   => $request->amount_paid,
            'payment_mode'  => $request->payment_mode,
            'customer_id'   => optional($arInvoice->sales_order)->customer_id,
        ]);

        // Update AR Invoice balance
        $arInvoice->amount_paid += $request->amount_paid;
        $arInvoice->balance_due  = $arInvoice->balance_due - $request->amount_paid;

        if ($arInvoice->balance_due <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('paid')->id;
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')->id;
        }

        $arInvoice->save();

        // Sync Sales Order status and create incentive on full payment
        $salesOrder = $arInvoice->sales_order;
        if ($salesOrder) {
            if ($arInvoice->balance_due <= 0) {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('closed')->id]);

                if (!SalesOrderIncentive::where('sales_order_id', $salesOrder->id)->exists()) {
                    $sold_quantity    = $salesOrder->items->sum('quantity');
                    $product_total_kg = $salesOrder->items->sum(fn($item) => $item->product->pack_size * $item->quantity);

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
                $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')->id]);
            }
        }

        $this->journalEntryService->recordReceiptEntry($receipt);

        return [
            'data'    => $receipt,
            'message' => 'Receipt saved successfully!',
            'info'    => "You've successfully saved the receipt",
        ];
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
            $arInvoice->status_id = ListStatus::getBySlug('paid')->id;
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')->id;
        } else {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')->id;
        }

        $arInvoice->save();

        // Sync receipt's own balance_due
        $receipt->update(['balance_due' => $arInvoice->balance_due]);

        // Sync Sales Order status
        $salesOrder = $arInvoice->sales_order;
        if ($salesOrder) {
            if ($arInvoice->balance_due <= 0) {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('closed')->id]);
            } elseif ($arInvoice->amount_paid > 0) {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')->id]);
            } else {
                $salesOrder->update(['status_id' => ListStatus::getBySlug('for-payment')->id]);
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
        $receipt = Receipt::findOrFail($id);
        $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt deleted. Original collection entry reversed.', now()->toDateString());
        $arInvoice = \App\Models\ArInvoice::find($receipt->ar_invoice_id);

        // Reverse the payment
        $arInvoice->amount_paid -= $receipt->amount_paid;
        $arInvoice->balance_due += $receipt->amount_paid;

        if ($arInvoice->amount_paid <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')->id;
        } else {
            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')->id;
        }

        $arInvoice->save();
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

    private function generateReceiptNumber(){
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastReceipt = Receipt::whereYear('created_at', $year)
                             ->whereMonth('created_at', $month)
                             ->orderBy('id', 'desc')
                             ->first();

        $sequence = $lastReceipt ? intval(substr($lastReceipt->receipt_number, -4)) + 1 : 1;

        return 'OR-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
