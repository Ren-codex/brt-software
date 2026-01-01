<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\DB;

use App\Models\ArInvoice;
use App\Models\SalesOrder;
use App\Http\Resources\Modules\ArInvoiceResource;


class ArInvoiceClass
{
    public function lists($request){
        $data = ArInvoiceResource::collection(
            ArInvoice::with(['salesOrder.customer', 'salesOrder.salesOrderItems.product', 'status'])
                ->when($request->keyword, function ($query,$keyword) {
                    $query->where('invoice_number', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('salesOrder', function($q) use ($keyword){
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

    public function save($request){
        DB::transaction(function () use ($request) {
            $salesOrder = SalesOrder::findOrFail($request->sales_order_id);

            // Create AR Invoice
            $data = new ArInvoice();
            $data->sales_order_id = $request->sales_order_id;
            $data->invoice_number = ArInvoice::generateInvoiceNumber();
            $data->invoice_date = $request->invoice_date ?? now();
            $data->amount_balance = $salesOrder->total_amount;
            $data->amount_paid = 0;
            $data->balance_due = $salesOrder->total_amount;
            $data->status_id = 2; // Unpaid
            $data->created_by = auth()->user()->id;
            $data->save();

            return $data;
        });

        return [
            'data' => ArInvoice::with(['salesOrder.customer', 'status'])->find($data->id),
            'message' => 'AR Invoice created successfully!',
            'info' => "You've successfully created the AR Invoice"
        ];
    }

    public function update($request){
        return DB::transaction(function () use ($request) {
            $data = ArInvoice::findOrFail($request->id);

            $data->update([
                'invoice_date' => $request->invoice_date,
                'status_id' => $request->status_id,
            ]);

            return [
                'data' => ArInvoice::with(['salesOrder.customer', 'status'])->find($data->id),
                'message' => 'AR Invoice updated successfully!',
                'info' => "You've successfully updated the AR Invoice"
            ];
        });
    }

    public function approve($id){
        $data = ArInvoice::findOrFail($id);

        $data->update([
            'status_id' => 4, // Approved
            'approved_by' => auth()->user()->id,
            'approved_at' => now(),
        ]);

        return [
            'data' => ArInvoice::find($id),
            'message' => 'AR Invoice approved successfully!',
            'info' => "You've successfully approved the AR Invoice"
        ];
    }

    public function cancel($id){
        $data = ArInvoice::findOrFail($id);

        $data->update([
            'status_id' => 5, // Cancelled
        ]);

        return [
            'data' => ArInvoice::find($id),
            'message' => 'AR Invoice cancelled successfully!',
            'info' => "You've successfully cancelled the AR Invoice"
        ];
    }

    public function dashboard(){
        $total_ar_invoices = ArInvoice::count();
        $today_invoices = ArInvoice::whereDate('created_at', today())->count();
        $total_revenue = ArInvoice::sum('amount_balance') ?? 0;
        $pending_invoices = ArInvoice::where('status_id', 2)->count(); // Unpaid
        $cancelled_invoices = ArInvoice::where('status_id', 5)->count(); // Cancelled

        return [
            'total_sales_orders' => $total_ar_invoices,
            'today_orders' => $today_invoices,
            'total_revenue' => $total_revenue,
            'pending_orders' => $pending_invoices,
            'cancelled_orders' => $cancelled_invoices,
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
}
