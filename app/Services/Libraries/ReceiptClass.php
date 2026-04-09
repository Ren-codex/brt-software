<?php

namespace App\Services\Libraries;


use App\Models\Receipt;
use App\Models\ListStatus;
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
        return [
            'total_receipts' => Receipt::where(function ($query) {
                $query->whereNull('receipt_type')
                    ->orWhere('receipt_type', '!=', 'refund');
            })->count(),
            'total_amount_collected' => Receipt::where(function ($query) {
                $query->whereNull('receipt_type')
                    ->orWhereNotIn('receipt_type', ['refund', 'updated']);
            })->sum('amount_paid'),
        ];
    }

    public function save($request){
        $arInvoice = \App\Models\ArInvoice::with('sales_order')->findOrFail($request->ar_invoice_id);

        $receipt = Receipt::create([
            'ar_invoice_id' => $request->ar_invoice_id,
            'status_id' => $request->status_id,
            'receipt_number' => $this->generateReceiptNumber(),
            'receipt_type' => 'payment',
            'receipt_date' => $request->receipt_date,
            'amount_paid' => $request->amount_paid,
            'payment_mode' => $request->payment_mode,
            'customer_id' => optional($arInvoice->sales_order)->customer_id,
        ]);

        // Update AR Invoice balance
        $arInvoice->amount_paid += $request->amount_paid;
        $arInvoice->balance_due = $arInvoice->balance_due - $request->amount_paid;

        // Update status based on balance
        if ($arInvoice->balance_due <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('paid')->id; // Paid
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially_paid')->id; // Partially Paid
        }

        $arInvoice->save();

        $this->journalEntryService->recordReceiptEntry($receipt);

        return [
            'data' => $receipt,
            'message' => 'Receipt saved successfully!',
            'info' => "You've successfully saved the receipt",
        ];
    }

    public function update($request){
        $receipt = Receipt::findOrFail($request->id);
        $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt updated. Previous receipt entry reversed.', $request->receipt_date);
        $oldAmount = $receipt->amount_paid;

        $receipt->update($request->only([
            'ar_invoice_id', 'status_id', 'receipt_date', 'amount_paid', 'payment_mode'
        ]));

        // Update AR Invoice balance
        $arInvoice = \App\Models\ArInvoice::find($receipt->ar_invoice_id);
        $arInvoice->amount_paid = $arInvoice->amount_paid - $oldAmount + $request->amount_paid;
        $arInvoice->balance_due = $arInvoice->balance_due + $oldAmount - $request->amount_paid;

        // Update status based on balance
        if ($arInvoice->balance_due <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('paid')->id; // Paid
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially_paid')->id; // Partially Paid
        } else {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')->id; // Unpaid
        }

        $arInvoice->save();

        $this->journalEntryService->recordReceiptEntry($receipt->fresh());

        return [
            'data' => $receipt,
            'message' => 'Receipt updated successfully!',
            'info' => "You've successfully updated the receipt",
        ];
    }

    public function delete($id){
        $receipt = Receipt::findOrFail($id);
        $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt deleted. Original collection entry reversed.', now()->toDateString());
        $arInvoice = \App\Models\ArInvoice::find($receipt->ar_invoice_id);

        // Reverse the payment
        $arInvoice->amount_paid -= $receipt->amount_paid;
        $arInvoice->balance_due += $receipt->amount_paid;

        // Update status
        if ($arInvoice->amount_paid == 0) {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')->id; // Unpaid
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially_paid')->id; // Partially Paid
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
