<?php

namespace App\Services;

use App\Models\ListAcademic;
use Carbon\Carbon;
use App\Models\SalesOrder;

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
            case 'invoice':
                return $this->printArInvoice($id);
            break;
        }
    }

    public function printSalesOrder($id){
        $sales_order = SalesOrder::with('status' , 'items.product.brand' , 'items.product.unit', 'created_by' )->findOrFail($id);
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

        $pdf = \PDF::loadView('prints.purchase-order',$array)->setPaper('A4', 'portrait');
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
        $ar_invoice = \App\Models\ArInvoice::with('status', 'salesOrder.customer', 'salesOrder.items.product.brand', 'salesOrder.items.product.unit', 'salesOrder.created_by', 'salesOrder.approved_by')->findOrFail($id);
        $sales_order = $ar_invoice->salesOrder;
        $items = $sales_order->items;

        $array = [
            'ar_invoice' => $ar_invoice,
            'sales_order' => $sales_order,
            'items' => $items,
        ];

        $pdf = \PDF::loadView('prints.sales_order',$array)->setPaper('A4', 'portrait');
        return $pdf->stream($ar_invoice->invoice_number.'.pdf');

    }

}
