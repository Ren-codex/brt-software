<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\DB;

use App\Models\ArInvoice;
use App\Models\SalesOrder;
use App\Models\Receipt;
use App\Models\ListStatus;
use App\Http\Resources\Modules\ArInvoiceResource;
use App\Models\SalesOrderIncentive;
use App\Services\PrintClass;
use App\Services\Accounting\JournalEntryService;


class ArInvoiceClass
{
    protected $print;

    public function __construct(PrintClass $print, protected JournalEntryService $journalEntryService)
    {
        $this->print = $print;
    }
    public function lists($request){
        $data = ArInvoiceResource::collection(
            ArInvoice::with(['sales_order.customer', 'sales_order.items.product', 'sales_order.status', 'status', 'receipts.status'])
                ->whereHas('sales_order', function ($q) {
                    $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(payment_mode)'), ['credit', 'credit sales']);
                })
                ->when($request->location_id, function ($query, $locationId) {
                    $query->whereHas('sales_order', function ($q) use ($locationId) {
                        $q->where('location_id', $locationId);
                    });
                })
                ->when($request->status, function ($query, $status) {
                    $query->whereHas('status', function ($q) use ($status) {
                        $q->where('slug', $status);
                    });
                })
                ->when($request->keyword, function ($query,$keyword) {
                    $query->where('invoice_number', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('sales_order', function($q) use ($keyword){
                              $q->where('so_number', 'LIKE', "%{$keyword}%")
                                ->orWhereHas('customer', function($cq) use ($keyword){
                                    $cq->where('name', 'LIKE', "%{$keyword}%");
                                });
                          })
                          ->orWhereHas('status', function($sq) use ($keyword){
                              $sq->where('name', 'LIKE', "%{$keyword}%");
                          });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }


    public function dashboard(){
        $cancelledId = ListStatus::getBySlug('cancelled')->id;

        $base = ArInvoice::where('status_id', '!=', $cancelledId);

        $total_invoices      = (clone $base)->count();
        $outstanding_balance = (clone $base)->sum('balance_due') ?? 0.00;
        $paid_invoices       = (clone $base)->where('balance_due', '<=', 0)->count();
        $pending_invoices    = (clone $base)->where('balance_due', '>', 0)->count();
        $today_invoices      = (clone $base)->whereDate('created_at', today())->count();

        return [
            'total_invoices'      => $total_invoices,
            'outstanding_balance' => (float) $outstanding_balance,
            'paid_invoices'       => $paid_invoices,
            'pending_invoices'    => $pending_invoices,
            'today_invoices'      => $today_invoices,
        ];
    }

    public function stockAvailability(){
        // AR Invoices don't directly relate to stock, return empty data
        return [
            'total_kg_left' => 0,
            'five_kg_sacks_left' => 0,
            'ten_kg_sacks_left' => 0,
            'twenty_five_kg_sacks_left' => 0,
            'products' => []
        ];
    }

    public function payment($request, $id = null){
        $ar_invoice = ArInvoice::findOrFail($request->id);

        $receipt = new Receipt();
        $receipt->receipt_number = Receipt::generateReceiptNumber();
        $receipt->receipt_date = $request->payment_date;
        $receipt->amount_paid = $request->amount_paid;
        $receipt->payment_mode = $request->payment_mode;
        $receipt->status_id = ListStatus::getBySlug('paid')->id;
        $receipt->customer_id = $ar_invoice->sales_order->customer_id;
        $receipt->ar_invoice_id = $ar_invoice->id;
        $receipt->save();


        // Update AR Invoice
        $ar_invoice->amount_paid =  $ar_invoice->amount_paid + $request->amount_paid;
        $ar_invoice->balance_due = $ar_invoice->balance_due - $request->amount_paid;


        // fetch Sales order
        $sales_order = SalesOrder::findOrFail($ar_invoice->sales_order_id);

        if ($ar_invoice->balance_due <= 0) {
            $ar_invoice->status_id = ListStatus::getBySlug('paid')->id;
            $sales_order->update([
                'status_id' => ListStatus::getBySlug('closed')->id,
            ]);

            $existingIncentive = SalesOrderIncentive::where('sales_order_id', $sales_order->id)->first();
            if (!$existingIncentive) {
                $sold_quantity    = $sales_order->items->sum('quantity');
                $product_total_kg = $sales_order->items->sum(fn($item) => $item->product->pack_size * $item->quantity);

                SalesOrderIncentive::create([
                    'sales_order_id'   => $sales_order->id,
                    'employee_id'      => $sales_order->sales_rep_id,
                    'sold_quantity'    => $sold_quantity,
                    'product_total_kg' => $product_total_kg,
                    'amount'           => $product_total_kg / 25,
                    'payroll_id'       => null,
                ]);
            }
        } else {
            $ar_invoice->status_id = ListStatus::getBySlug('partially-paid')->id;
            $sales_order->update([
                'status_id' => ListStatus::getBySlug('partially-paid')->id,
            ]);
        }
        $ar_invoice->save();

        $receipt = Receipt::findOrFail($receipt->id);
        $receipt->update([
            'balance_due' => $ar_invoice->balance_due,
        ]);

        $this->journalEntryService->recordReceiptEntry($receipt->fresh());

        
        return [
            'data' => new ArInvoiceResource($ar_invoice),
            'receipt_id' => $receipt->id,
            'message' => 'Payment saved successfully!',
            'info' => "Payment successfully saved"
        ];
    }

    public function print($request){
        $arInvoice = ArInvoice::with(['sales_order.customer', 'sales_order.items.product', 'status'])->find($request->id);
        return $this->print->generate($arInvoice, 'ar_invoice');
    }
}
