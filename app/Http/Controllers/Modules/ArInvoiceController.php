<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\ArInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PrintClass;

class ArInvoiceController extends Controller
{
    public $print;

    public function __construct(PrintClass $print)
    {
        $this->print = $print;
    }
    public function index(Request $request)
    {
        $query = ArInvoice::with(['salesOrder.customer', 'salesOrder.salesOrderItems.product', 'status']);

        // Search functionality
        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('invoice_number', 'like', '%' . $keyword . '%')
                  ->orWhereHas('salesOrder', function ($sq) use ($keyword) {
                      $sq->where('so_number', 'like', '%' . $keyword . '%')
                        ->orWhereHas('customer', function ($cq) use ($keyword) {
                            $cq->where('name', 'like', '%' . $keyword . '%');
                        });
                  });
            });
        }

        // Dashboard metrics
        if ($request->option === 'dashboard') {
            return $this->getDashboardMetrics();
        }

        // Stock information
        if ($request->option === 'stock') {
            return $this->getStockInfo();
        }

        $arInvoices = $query->orderBy('created_at', 'desc')
                           ->paginate($request->get('count', 10));

        return back()->with($arInvoices);
    }

    public function show($id, Request $request)
    {
        if ($request->type === 'print') {
            return $this->print->print($id, $request);
        }

        $arInvoice = ArInvoice::with(['salesOrder.customer', 'salesOrder.salesOrderItems.product', 'status'])
                             ->findOrFail($id);

        return back()->with($arInvoice);
    }

    private function getDashboardMetrics()
    {
        $totalArInvoices = ArInvoice::count();
        $todayArInvoices = ArInvoice::whereDate('created_at', today())->count();
        $totalRevenue = ArInvoice::sum('amount_balance');
        $pendingInvoices = ArInvoice::where('status_id', 2)->count(); // Assuming 2 is unpaid
        $cancelledInvoices = ArInvoice::where('status_id', 5)->count(); // Assuming 5 is cancelled

        return back()->with([
            'total_sales_orders' => $totalArInvoices,
            'today_orders' => $todayArInvoices,
            'total_revenue' => $totalRevenue ?? 0,
            'pending_orders' => $pendingInvoices,
            'cancelled_orders' => $cancelledInvoices
        ]);
    }

    private function getStockInfo()
    {
        // This might need to be adjusted based on your actual stock logic
        // For now, returning empty stock info since AR invoices don't directly relate to stock
        return back()->with([
            'total_kg_left' => 0,
            'five_kg_sacks_left' => 0,
            'ten_kg_sacks_left' => 0,
            'twenty_five_kg_sacks_left' => 0,
            'products' => []
        ]);
    }
}
