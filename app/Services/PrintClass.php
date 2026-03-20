<?php

namespace App\Services;

use App\Models\ListAcademic;
use Carbon\Carbon;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class PrintClass
{  

    public function print($id, $request){

        switch($request->type){
            case 'sales_order':
                return $this->printSalesOrder($id);
            break;
            case 'purchase_order':
                return $this->prinPurchaseOrder($id);
            break;
            case 'remittance':
                return $this->printRemittance($id);
            break;
            case 'ar_invoice':
                return $this->printArInvoice($id);
            break;
            case 'receipt':
                return $this->printReceipt($id);
            break;
            case 'payroll':
                return $this->printPayroll($id);
            break;
        }
    }

    public function printSalesOrder($id){
        $sales_order = SalesOrder::with('status' , 'customer', 'items.product.brand' , 'items.product.unit', 'created_by' )->findOrFail($id);
        $items = $sales_order->items;

        $array = [
            'sales_order' => $sales_order,
            'items' => $items,
        ];

        $pdf = \PDF::loadView('prints.sales_order',$array)->setPaper('A4', 'portrait');
        return $pdf->stream($sales_order->so_number.'.pdf');

    }

    public function prinPurchaseOrder($id){
        $purchase_order = \App\Models\PurchaseOrder::with('status' , 'items.product.brand', 'items.product.unit', 'supplier')->findOrFail($id);
        $items = $purchase_order->items;

        $array = [
            'purchase_order' => $purchase_order,
            'items' => $items,
        ];


        $pdf = \PDF::loadView('prints.purchase-order',$array)->setPaper('A4', 'landscape');
        return $pdf->stream('purchase-order-'.$purchase_order->po_number.'.pdf');

    }

    public function printRemittance($id){
        $remittance = \App\Models\Remittance::with('status', 'receipts.customer', 'createdBy', 'approvedBy')->findOrFail($id);
        $receipts = $remittance->receipts;

        $array = [
            'remittance' => $remittance,
            'receipts' => $receipts,
        ];

        $pdf = \PDF::loadView('prints.remittance',$array)->setPaper('A4', 'portrait');
        return $pdf->stream('remittance-'.$remittance->remittance_no.'.pdf');

    }

    public function printArInvoice($id){
        $ar_invoice = \App\Models\ArInvoice::with('status', 'sales_order.customer', 'sales_order.items.product.brand', 'sales_order.items.product.unit', 'sales_order.salesRep', 'sales_order.created_by.employee', 'sales_order.approved_by')->findOrFail($id);
        $sales_order = $ar_invoice->sales_order;
        $items = $sales_order->items;

        $array = [
            'ar_invoice' => $ar_invoice,
            'sales_order' => $sales_order,
            'items' => $items,
        ];

        $pdf = \PDF::loadView('prints.ar_invoice',$array)->setPaper('A4', 'portrait');
        return $pdf->stream($ar_invoice->invoice_number.'.pdf');

    }

    public function printReceipt($id){
        $receipt = \App\Models\Receipt::with('status', 'customer', 'sourceReceipt.sourceReceipt', 'arInvoice.sales_order.items.product.brand', 'arInvoice.sales_order.items.product.unit', 'arInvoice.sales_order.customer' , 'arInvoice.sales_order.salesRep')->findOrFail($id);

        $ar_invoice = $receipt->arInvoice;
        $sales_order = $ar_invoice ? $ar_invoice->sales_order : null;
        $items = $sales_order ? $sales_order->items : collect();
        $displayAmount = (float) ($receipt->amount_paid ?? 0);
        $voidedReceipt = null;

        if (($receipt->receipt_type ?? 'payment') === 'refund' && $sales_order) {
            $sourceReceiptId = $receipt->source_receipt_id;
            $voidedReceipt = $receipt->sourceReceipt;

            $returnedItems = DB::table('sales_return_items as sri')
                ->join('sales_order_items as soi', 'soi.id', '=', 'sri.sales_order_item_id')
                ->where('soi.sales_order_id', $sales_order->id)
                ->when($sourceReceiptId, function ($query, $sourceReceiptId) {
                    $query->where('sri.source_receipt_id', $sourceReceiptId);
                })
                ->select('sri.sales_order_item_id', 'sri.return_quantity')
                ->pluck('return_quantity', 'sales_order_item_id');

            $items = $sales_order->items
                ->whereIn('id', $returnedItems->keys())
                ->map(function ($item) use ($returnedItems) {
                    $item->quantity = (int) ($returnedItems[$item->id] ?? $item->quantity);
                    return $item;
                })
                ->filter(fn ($item) => (int) $item->quantity > 0)
                ->values();
        }

        if (($receipt->receipt_type ?? 'payment') === 'updated' && $sales_order) {
            $refundReceipt = $receipt->sourceReceipt;
            $originalReceipt = optional($refundReceipt)->sourceReceipt;
            $sourceReceiptId = $originalReceipt?->id;
            $voidedReceipt = $originalReceipt;

            $returnedItems = DB::table('sales_return_items as sri')
                ->join('sales_order_items as soi', 'soi.id', '=', 'sri.sales_order_item_id')
                ->where('soi.sales_order_id', $sales_order->id)
                ->when($sourceReceiptId, function ($query, $sourceReceiptId) {
                    $query->where('sri.source_receipt_id', $sourceReceiptId);
                })
                ->select('sri.sales_order_item_id', 'sri.return_quantity')
                ->pluck('return_quantity', 'sales_order_item_id');

            $items = $sales_order->items
                ->map(function ($item) use ($returnedItems) {
                    $returnedQuantity = (int) ($returnedItems[$item->id] ?? 0);
                    $item->quantity = max(0, (int) $item->quantity - $returnedQuantity);
                    return $item;
                })
                ->filter(fn ($item) => (int) $item->quantity > 0)
                ->values();
        }

        $array = [
            'receipt' => $receipt,
            'ar_invoice' => $ar_invoice,
            'sales_order' => $sales_order,
            'items' => $items,
            'display_amount' => $displayAmount,
            'voided_receipt' => $voidedReceipt,
        ];


        $pdf = \PDF::loadView('prints.receipt',$array)->setPaper('A4', 'portrait');
        return $pdf->stream($receipt->receipt_number.'.pdf');

    }

    public function printPayroll($id){
        $payroll = \App\Models\Payroll::with('items.employee', 'template', 'creator', 'approvedBy.employee')->findOrFail($id);
        $items = $payroll->items;

        $array = [
            'payroll' => $payroll,
            'items' => $items,
        ];

        $pdf = \PDF::loadView('prints.payroll',$array)->setPaper('A4', 'portrait');
        return $pdf->stream($payroll->payroll_no.'.pdf');

    }

}
