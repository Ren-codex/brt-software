<?php

namespace App\Services;

use App\Models\ListAcademic;
use Carbon\Carbon;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class PrintClass
{
    /**
     * 8½ × 13 inches in points, at 72 to the inch: Philippine long bond, the
     * paper these documents are actually printed on. DomPDF takes the page box
     * from here, so the @page rule in each view only has to agree with it.
     */
    private const LONG_BOND = [0, 0, 612, 936];


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
        $sales_order = SalesOrder::with('status' , 'customer', 'location', 'salesRep', 'driver', 'checker', 'items.product.brand' , 'items.product.unit', 'items.product.packaging', 'created_by' )->findOrFail($id);
        $items = $sales_order->items;

        $array = [
            'sales_order' => $sales_order,
            'items' => $items,
        ];

        $pdf = \PDF::loadView('prints.sales_order',$array)->setPaper(self::LONG_BOND);
        return $pdf->stream($sales_order->so_number.'.pdf');

    }

    public function prinPurchaseOrder($id){
        $purchase_order = \App\Models\PurchaseOrder::with('status' , 'items.product.brand', 'items.product.unit', 'supplier')->findOrFail($id);
        $items = $purchase_order->items;

        $array = [
            'purchase_order' => $purchase_order,
            'items' => $items,
        ];


        $pdf = \PDF::loadView('prints.purchase-order',$array)->setPaper(self::LONG_BOND, 'landscape');
        return $pdf->stream('purchase-order-'.$purchase_order->po_number.'.pdf');

    }

    public function printRemittance($id){
        $remittance = \App\Models\Remittance::with('status', 'receipts.customer', 'createdBy', 'approvedBy')->findOrFail($id);
        $receipts = $remittance->receipts;

        $array = [
            'remittance' => $remittance,
            'receipts' => $receipts,
        ];

        $pdf = \PDF::loadView('prints.remittance',$array)->setPaper(self::LONG_BOND);
        return $pdf->stream('remittance-'.$remittance->remittance_no.'.pdf');

    }

    /**
     * The most item rows one copy can hold and still fit the top half of a long
     * bond sheet, measured against the rendered PDF (see the print templates).
     * Above this, each copy is printed on a sheet of its own rather than being
     * split across the cut. Re-measure if a template's font or layout changes.
     *
     * Both measured at five rows with a discount line showing; set one lower so
     * a product name wrapping onto a second line, or a refund receipt's extra
     * remark lines, still clears the cut.
     */
    public const RECEIPT_ROWS_PER_HALF_SHEET = 4;

    public const INVOICE_ROWS_PER_HALF_SHEET = 4;

    public function printArInvoice($id){
        $ar_invoice = \App\Models\ArInvoice::with('status', 'sales_order.customer', 'sales_order.items.product.brand', 'sales_order.items.product.unit', 'sales_order.salesRep', 'sales_order.created_by.employee', 'sales_order.approved_by')->findOrFail($id);
        $sales_order = $ar_invoice->sales_order;
        $items = $sales_order->items;

        $array = [
            'ar_invoice' => $ar_invoice,
            'sales_order' => $sales_order,
            'items' => $items,
        ];

        $pdf = \PDF::loadView('prints.ar_invoice',$array)->setPaper(self::LONG_BOND);
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


        $pdf = \PDF::loadView('prints.receipt',$array)->setPaper(self::LONG_BOND);
        return $pdf->stream($receipt->receipt_number.'.pdf');

    }

    /**
     * One payslip per employee on the payroll, or a single one when an
     * employee is named. Built from the saved payroll lines, so a payslip
     * always agrees with the payroll it came from.
     */
    public function printPayslips($id, $employeeId = null)
    {
        $payroll = \App\Models\Payroll::with('items.employee.position', 'creator.employee')->findOrFail($id);

        $items = $payroll->items
            ->when($employeeId, fn ($items) => $items->where('employee_id', (int) $employeeId))
            ->sortBy(fn ($item) => $item->employee?->fullname ?? '')
            ->values();

        abort_if($employeeId && $items->isEmpty(), 404, 'That employee is not on this payroll.');

        // Everyone at once goes four to a sheet, to be cut into quarters; one
        // employee on their own keeps the larger slip.
        $pdf = \PDF::loadView('prints.payslip', [
            'payroll' => $payroll,
            'items' => $items,
            'fourUp' => ! $employeeId,
        ])->setPaper(self::LONG_BOND);

        $suffix = $employeeId ? '-'.\Illuminate\Support\Str::slug($items->first()->employee?->fullname ?? 'employee') : '';

        return $pdf->stream($payroll->payroll_no.'-payslips'.$suffix.'.pdf');
    }

    /**
     * A full-page pay statement for one employee on a payroll.
     *
     * The same figures as the payslip, laid out as a document to file or hand
     * over rather than a slip to cut out: it adds how each amount was arrived
     * at (rate × days, overtime hours × rate), any loans deducted, and a line
     * for the approver's signature.
     */
    public function printPayrollStatement($id, $employeeId)
    {
        $payroll = \App\Models\Payroll::with('status', 'items.employee.position', 'creator.employee', 'approvedBy.employee')->findOrFail($id);
        $item = $payroll->items->firstWhere('employee_id', (int) $employeeId);

        abort_unless($item, 404, 'That employee is not on this payroll.');

        $pdf = \PDF::loadView('prints.payroll-statement', ['payroll' => $payroll, 'item' => $item])
            ->setPaper(self::LONG_BOND);

        return $pdf->stream($payroll->payroll_no.'-statement-'.\Illuminate\Support\Str::slug($item->employee?->fullname ?? 'employee').'.pdf');
    }

    public function printPayroll($id){
        $payroll = \App\Models\Payroll::with('items.employee', 'template', 'creator', 'approvedBy.employee')->findOrFail($id);
        $items = $payroll->items;

        $array = [
            'payroll' => $payroll,
            'items' => $items,
        ];

        $pdf = \PDF::loadView('prints.payroll',$array)->setPaper(self::LONG_BOND);
        return $pdf->stream($payroll->payroll_no.'.pdf');

    }

}
