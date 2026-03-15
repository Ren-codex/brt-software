<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Models\SalesOrder;
use App\Models\Receipt;
use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\InventoryStocks;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{
    protected $dropdown;

    public function __construct(DropdownClass $dropdown)
    {
        $this->dropdown = $dropdown;
    }

    public function index(Request $request){
        // Get filter from request, default to monthly
        $filter = $request->get('filter', 'monthly');
        $selectedDate = $request->get('selected_date');
        
        // Calculate date range based on filter
        $dateRange = $this->getDateRange($filter, $selectedDate);
        
        // Sales dashboard uses liquidated remittances with realized margin-based revenue.
        $successfulSalesOrders = DB::table('sales_orders as so')
            ->join('ar_invoices as ai', 'so.id', '=', 'ai.sales_order_id')
            ->join('receipts as r', 'ai.id', '=', 'r.ar_invoice_id')
            ->join('remittances as rem', 'r.remittance_id', '=', 'rem.id')
            ->join('list_statuses as rem_status', 'rem.status_id', '=', 'rem_status.id')
            ->where('rem_status.slug', 'liquidated')
            ->whereBetween('rem.remittance_date', [$dateRange['start'], $dateRange['end']])
            ->distinct()
            ->select('so.id');

        $revenueBaseQuery = DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'soi.sales_order_id', '=', 'so.id')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
            ->join('inventory_stocks as inv', 'soi.batch_code', '=', 'inv.batch_code')
            ->join('received_items as ri', function ($join) {
                $join->on('inv.received_item_id', '=', 'ri.id')
                    ->on('soi.product_id', '=', 'ri.product_id');
            })
            ->whereIn('so.id', $successfulSalesOrders);

        $totalSales = (float) (clone $revenueBaseQuery)
            ->selectRaw('COALESCE(SUM(((soi.price - COALESCE(soi.discount_per_unit, 0)) - COALESCE(ri.unit_cost, 0)) * soi.quantity), 0) as total_revenue')
            ->value('total_revenue');

        $totalReceipts = Receipt::query()
            ->join('remittances as rem', 'receipts.remittance_id', '=', 'rem.id')
            ->join('list_statuses as rem_status', 'rem.status_id', '=', 'rem_status.id')
            ->where('rem_status.slug', 'liquidated')
            ->whereBetween('rem.remittance_date', [$dateRange['start'], $dateRange['end']])
            ->distinct('receipts.id')
            ->count('receipts.id');

        $totalOutstanding = ArInvoice::sum('balance_due');
        $totalCustomers = Receipt::query()
            ->join('remittances as rem', 'receipts.remittance_id', '=', 'rem.id')
            ->join('list_statuses as rem_status', 'rem.status_id', '=', 'rem_status.id')
            ->where('rem_status.slug', 'liquidated')
            ->whereBetween('rem.remittance_date', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('receipts.customer_id')
            ->distinct('receipts.customer_id')
            ->count('receipts.customer_id');
        $avgOrderValue = $totalReceipts > 0 ? round($totalSales / $totalReceipts, 2) : 0;

        // Monthly realized revenue for the last 12 months from liquidated remittances.
        $monthlySales = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('M Y');
            $monthlySuccessfulSalesOrders = DB::table('sales_orders as so')
                ->join('ar_invoices as ai', 'so.id', '=', 'ai.sales_order_id')
                ->join('receipts as r', 'ai.id', '=', 'r.ar_invoice_id')
                ->join('remittances as rem', 'r.remittance_id', '=', 'rem.id')
                ->join('list_statuses as rem_status', 'rem.status_id', '=', 'rem_status.id')
                ->where('rem_status.slug', 'liquidated')
                ->whereYear('rem.remittance_date', $date->year)
                ->whereMonth('rem.remittance_date', $date->month)
                ->distinct()
                ->select('so.id');

            $sales = (float) (clone $revenueBaseQuery)
                ->whereIn('so.id', $monthlySuccessfulSalesOrders)
                ->selectRaw('COALESCE(SUM(((soi.price - COALESCE(soi.discount_per_unit, 0)) - COALESCE(ri.unit_cost, 0)) * soi.quantity), 0) as total_revenue')
                ->value('total_revenue');
            $monthlySales[] = [
                'month' => $month,
                'sales' => (float) $sales
            ];
        }

        $paymentMethods = Receipt::query()
            ->join('remittances as rem', 'receipts.remittance_id', '=', 'rem.id')
            ->join('list_statuses as rem_status', 'rem.status_id', '=', 'rem_status.id')
            ->where('rem_status.slug', 'liquidated')
            ->whereBetween('rem.remittance_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('COALESCE(receipts.payment_mode, "Cash") as method, SUM(receipts.amount_paid) as total')
            ->groupBy('method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->method ?: 'Cash',
                    'total' => (float) $item->total
                ];
            });

        $topProductsQuery = (clone $revenueBaseQuery)
            ->select(
                'p.id',
                DB::raw('CONCAT(p.pack_size, " ", lu.name, " ", lb.name) as name'),
                DB::raw('lb.name as brand'),
                DB::raw('SUM(soi.quantity) as quantity_sold'),
                DB::raw('SUM(((soi.price - COALESCE(soi.discount_per_unit, 0)) - COALESCE(ri.unit_cost, 0)) * soi.quantity) as revenue')
            )
            ->groupBy('p.id', 'p.pack_size', 'lu.name', 'lb.name')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get();

        // Calculate percentage for each product
        $maxQuantity = $topProductsQuery->max('quantity_sold');
        $topProducts = $topProductsQuery->map(function ($item) use ($maxQuantity) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'brand' => $item->brand,
                'quantity_sold' => (int) $item->quantity_sold,
                'revenue' => (float) $item->revenue,
                'percentage' => $maxQuantity > 0 ? round(($item->quantity_sold / $maxQuantity) * 100) : 0
            ];
        });

        $recentTransactions = Receipt::with(['customer', 'status', 'remittance.status', 'arInvoice.sales_order.items'])
            ->whereHas('remittance.status', function ($query) {
                $query->where('slug', 'liquidated');
            })
            ->whereHas('remittance', function ($query) use ($dateRange) {
                $query->whereBetween('remittance_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $salesOrder = optional($item->arInvoice)->sales_order;
                $receiptDate = $item->receipt_date ? Carbon::parse($item->receipt_date)->format('Y-m-d') : optional($item->created_at)->format('Y-m-d');

                return [
                    'id' => $item->id,
                    'receipt_number' => $item->receipt_number ?: ('RCP-' . str_pad($item->id, 5, '0', STR_PAD_LEFT)),
                    'customer_name' => $item->customer->name ?? 'Walk-in Customer',
                    'date' => $receiptDate,
                    'items_count' => $salesOrder ? $salesOrder->items->count() : 0,
                    'amount' => (float) $item->amount_paid,
                    'payment_method' => $item->payment_mode ?: 'Cash',
                    'status' => optional($item->status)->name ?: 'Liquidated',
                ];
            });

        // Inventory Statistics (filtered by selected date range)
        $inventoryStocksInRange = InventoryStocks::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $totalProducts = Product::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
        $totalInventoryValue = (clone $inventoryStocksInRange)->sum(\DB::raw('retail_price * quantity'));
        $lowStockItems = (clone $inventoryStocksInRange)->where('quantity', '<=', 10)->count();
        $outOfStock = (clone $inventoryStocksInRange)->where('quantity', '<=', 0)->count();

        // Stock by Category for bar chart
        $stockByCategory = \DB::table('inventory_stocks')
            ->join('received_items', 'inventory_stocks.received_item_id', '=', 'received_items.id')
            ->join('products', 'received_items.product_id', '=', 'products.id')
            ->join('list_brands', 'products.brand_id', '=', 'list_brands.id')
            ->select('list_brands.name as category', \DB::raw('SUM(inventory_stocks.quantity) as quantity'))
            ->whereBetween('inventory_stocks.created_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('list_brands.name')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'quantity' => (int) $item->quantity
                ];
            });

        // Stock Distribution for donut chart
        $inStockCount = (clone $inventoryStocksInRange)->where('quantity', '>', 10)->count();
        $lowStockCount = (clone $inventoryStocksInRange)->where('quantity', '<=', 10)->where('quantity', '>', 0)->count();
        $outOfStockCount = (clone $inventoryStocksInRange)->where('quantity', '<=', 0)->count();
        
        $stockDistribution = [
            ['status' => 'In Stock', 'percentage' => $inStockCount],
            ['status' => 'Low Stock', 'percentage' => $lowStockCount],
            ['status' => 'Out of Stock', 'percentage' => $outOfStockCount],
        ];

        // Pending Purchase Orders
        $pendingPOs = PurchaseOrder::whereHas('status', function($q) {
                $q->where('name', 'Pending');
            })
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        // Low Stock Items for table
        $lowStockItemsData = \DB::table('inventory_stocks')
            ->join('received_items', 'inventory_stocks.received_item_id', '=', 'received_items.id')
            ->join('products', 'received_items.product_id', '=', 'products.id')
            ->join('list_brands', 'products.brand_id', '=', 'list_brands.id')
            ->select(
                'products.id',
                'products.id as code',
                'list_brands.name as category',
                'inventory_stocks.quantity as current_stock',
                \DB::raw('10 as minimum_stock')
            )
            ->whereBetween('inventory_stocks.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('inventory_stocks.quantity', '<=', 10)
            ->where('inventory_stocks.quantity', '>', 0)
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'code' => 'PRD-' . str_pad($item->id, 3, '0', STR_PAD_LEFT),
                    'name' => 'Product ' . $item->id, // Placeholder - would need product name field
                    'category' => $item->category,
                    'current_stock' => (int) $item->current_stock,
                    'minimum_stock' => (int) $item->minimum_stock,
                ];
            });

        return inertia('Modules/Dashboard/Index', [

            'stats' => [
                'totalSales' => $totalSales,
                'totalReceipts' => $totalReceipts,
                'totalOutstanding' => $totalOutstanding,
                'totalCustomers' => $totalCustomers,
                'avgOrderValue' => $avgOrderValue,
            ],
            'charts' => [
                'monthlySales' => $monthlySales,
                'paymentMethods' => $paymentMethods,
                'topProducts' => $topProducts,
            ],
            'recentTransactions' => $recentTransactions,
            'inventoryStats' => [
                'totalProducts' => $totalProducts,
                'totalValue' => $totalInventoryValue,
                'lowStockItems' => $lowStockItems,
                'outOfStock' => $outOfStock,
                'pendingPOs' => $pendingPOs,
            ],
            'inventoryCharts' => [
                'stockByCategory' => $stockByCategory,
                'stockDistribution' => $stockDistribution,
            ],
            'lowStockItems' => $lowStockItemsData,
            'filter' => $filter,
            'selectedDate' => optional($dateRange['selectedDate'])->toDateString()
        ]);

    }

    private function getDateRange($filter, $selectedDate = null)
    {
        $now = Carbon::now();
        $resolvedDate = $selectedDate ? Carbon::parse($selectedDate) : $now->copy();
        
        switch ($filter) {
            case 'today':
                return [
                    'start' => $resolvedDate->copy()->startOfDay(),
                    'end' => $resolvedDate->copy()->endOfDay(),
                    'selectedDate' => $resolvedDate->copy()
                ];
            case 'weekly':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek(),
                    'selectedDate' => $resolvedDate->copy()
                ];
            case 'monthly':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                    'selectedDate' => $resolvedDate->copy()
                ];
            case 'annually':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear(),
                    'selectedDate' => $resolvedDate->copy()
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                    'selectedDate' => $resolvedDate->copy()
                ];
        }
    }
}
