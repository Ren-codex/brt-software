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
        // Sales Statistics
        $totalSales = SalesOrder::sum('total_amount');
        $totalReceipts = Receipt::count();
        $totalOutstanding = ArInvoice::sum('balance_due');
        $totalCustomers = SalesOrder::distinct('customer_id')->count('customer_id');

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
            'lowStockItems' => $lowStockItemsData
        ]);

    }
}
