<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Models\SalesOrder;
use App\Models\Receipt;
use App\Models\ArInvoice;
use App\Models\Customer;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $dropdown;

    public function __construct(
            DropdownClass $dropdown
        ){
        $this->dropdown = $dropdown;
    }

    public function index(Request $request){
        // Statistics
        $totalSales = SalesOrder::sum('total_amount');
        $totalReceipts = Receipt::sum('amount_paid');
        $totalOutstanding = ArInvoice::sum('balance_due');
        $totalCustomers = Customer::count();

        // Monthly sales data for chart (last 12 months)
        $monthlySales = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('M Y');
            $sales = SalesOrder::whereYear('order_date', $date->year)
                              ->whereMonth('order_date', $date->month)
                              ->sum('total_amount');
            $monthlySales[] = [
                'month' => $month,
                'sales' => (float) $sales
            ];
        }

        // Payment methods distribution
        $paymentMethods = SalesOrder::selectRaw('payment_mode, SUM(total_amount) as total')
                                ->groupBy('payment_mode')
                                ->get()
                                ->map(function ($item) {
                                    return [
                                        'method' => $item->payment_mode ?: 'Cash',
                                        'total' => (float) $item->total
                                    ];
                                });

        return inertia('Modules/Dashboard/Index', [
            'stats' => [
                'totalSales' => $totalSales,
                'totalReceipts' => $totalReceipts,
                'totalOutstanding' => $totalOutstanding,
                'totalCustomers' => $totalCustomers,
            ],
            'charts' => [
                'monthlySales' => $monthlySales,
                'paymentMethods' => $paymentMethods,
            ]
        ]);
    }


}
