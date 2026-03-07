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
        
        // Calculate date range based on filter
        $dateRange = $this->getDateRange($filter);
        
        // Sales Statistics with date filter
        $totalSales = SalesOrder::whereBetween('order_date', [$dateRange['start'], $dateRange['end']])->sum('total_amount');
        $totalReceipts = Receipt::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
        $totalOutstanding = ArInvoice::sum('balance_due');
        $totalCustomers = SalesOrder::distinct('customer_id')->count('customer_id');

        // Monthly sales data for chart (last 12 months) - filtered by same date range
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

        // Payment methods distribution with date filter
        $paymentMethods = SalesOrder::whereBetween('order_date', [$dateRange['start'], $dateRange['end']])
                                ->selectRaw('payment_mode, SUM(total_amount) as total')
                                ->groupBy('payment_mode')
                                ->get()
                                ->map(function ($item) {
                                    return [
                                        'method' => $item->payment_mode ?: 'Cash',
                                        'total' => (float) $item->total
                                    ];
                                });

        // Top Selling Products with date filter
        $topProductsQuery = \DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('products', 'sales_order_items.product_id', '=', 'products.id')
            ->join('list_brands', 'products.brand_id', '=', 'list_brands.id')
            ->join('list_units', 'products.unit_id', '=', 'list_units.id')
            ->select(
                'products.id',
                \DB::raw('CONCAT(products.pack_size, " ", list_units.name, " ", list_brands.name) as name'),
                \DB::raw('list_brands.name as brand'),
                \DB::raw('SUM(sales_order_items.quantity) as quantity_sold'),
                \DB::raw('SUM(sales_order_items.price * sales_order_items.quantity) as revenue')
            )
            ->whereBetween('sales_orders.order_date', [$dateRange['start'], $dateRange['end']])
            ->groupBy('products.id', 'products.pack_size', 'list_units.name', 'list_brands.name')
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

        // Recent Transactions
        $recentTransactions = SalesOrder::with('customer')
                                ->orderBy('order_date', 'desc')
                                ->orderBy('id', 'desc')
                                ->limit(5)
                                ->get()
                                ->map(function ($item) {
                                    return [
                                        'id' => $item->id,
                                        'receipt_number' => 'SO-' . str_pad($item->id, 5, '0', STR_PAD_LEFT),
                                        'customer_name' => $item->customer->name ?? 'Walk-in Customer',
                                        'date' => $item->order_date,
                                        'amount' => (float) $item->total_amount,
                                        'payment_method' => $item->payment_mode ?: 'Cash',
                                    ];
                                });

        // Inventory Statistics
        $totalProducts = Product::count();
        $totalInventoryValue = InventoryStocks::sum(\DB::raw('retail_price * quantity'));
        $lowStockItems = InventoryStocks::whereColumn('quantity', '<=', \DB::raw('10'))->count(); // Using 10 as default minimum
        $outOfStock = InventoryStocks::where('quantity', '<=', 0)->count();

        // Stock by Category for bar chart
        $stockByCategory = \DB::table('inventory_stocks')
            ->join('received_items', 'inventory_stocks.received_item_id', '=', 'received_items.id')
            ->join('products', 'received_items.product_id', '=', 'products.id')
            ->join('list_brands', 'products.brand_id', '=', 'list_brands.id')
            ->select('list_brands.name as category', \DB::raw('SUM(inventory_stocks.quantity) as quantity'))
            ->groupBy('list_brands.name')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'quantity' => (int) $item->quantity
                ];
            });

        // Stock Distribution for donut chart
        $inStockCount = InventoryStocks::where('quantity', '>', 10)->count();
        $lowStockCount = InventoryStocks::whereColumn('quantity', '<=', \DB::raw('10'))->where('quantity', '>', 0)->count();
        $outOfStockCount = InventoryStocks::where('quantity', '<=', 0)->count();
        
        $stockDistribution = [
            ['status' => 'In Stock', 'percentage' => $inStockCount],
            ['status' => 'Low Stock', 'percentage' => $lowStockCount],
            ['status' => 'Out of Stock', 'percentage' => $outOfStockCount],
        ];

        // Pending Purchase Orders
        $pendingPOs = PurchaseOrder::whereHas('status', function($q) {
            $q->where('name', 'Pending');
        })->count();

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
            'filter' => $filter
        ]);

    }

    private function getDateRange($filter)
    {
        $now = Carbon::now();
        
        switch ($filter) {
            case 'today':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay()
                ];
            case 'weekly':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek()
                ];
            case 'monthly':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
            case 'annually':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear()
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
        }
    }
}
