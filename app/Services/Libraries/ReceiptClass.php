<?php

namespace App\Services\Libraries;


use App\Models\Receipt;
use App\Models\ListStatus;
use App\Http\Resources\Libraries\ReceiptResource;


class ReceiptClass
{
    public function lists($request){
        return Receipt::with(['arInvoice.sales_order.customer', 'status'])
            ->when($request->keyword, function($query) use ($request) {
                $query->whereHas('arInvoice.sales_order.customer', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->keyword . '%');
                })->orWhere('receipt_number', 'like', '%' . $request->keyword . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->count ?? 10);
    }

    public function dashboard(){
        return [
            'total_receipts' => Receipt::count(),
            'total_amount_collected' => Receipt::sum('amount_paid'),
        ];
    }

    public function save($request){
        $receipt = Receipt::create([
            'ar_invoice_id' => $request->ar_invoice_id,
            'status_id' => $request->status_id,
            'receipt_number' => $this->generateReceiptNumber(),
            'receipt_date' => $request->receipt_date,
            'amount_paid' => $request->amount_paid,
            'payment_mode' => $request->payment_mode,
            'created_by' => auth()->id(),
        ]);

        // Update AR Invoice balance
        $arInvoice = \App\Models\ArInvoice::find($request->ar_invoice_id);
        $arInvoice->amount_paid += $request->amount_paid;
        $arInvoice->balance_due = $arInvoice->balance_due - $request->amount_paid;

        // Update status based on balance
        if ($arInvoice->balance_due <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('paid')->id; // Paid
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially_paid')->id; // Partially Paid
        }

        $arInvoice->save();

        return $receipt;
    }

    public function update($request){
        $receipt = Receipt::findOrFail($request->id);
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

        return $receipt;
    }

    public function delete($id){
        $receipt = Receipt::findOrFail($id);
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

        return $receipt;
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
