<?php

namespace App\Services\Modules;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportClass
{
    public function summary(array $filters): array
    {
        return [
            'top_customers' => $this->topCustomers($filters),
            'top_products' => $this->topProducts($filters),
            'product_sales_report' => $this->productSalesReport($filters),
            'customer_sales_report' => $this->customerSalesReport($filters),
            'sales_rep_report' => $this->salesRepReport($filters),
            'daily_sales_orders' => $this->dailySalesOrders($filters),
            'payment_summary' => $this->paymentSummary($filters),
        ];
    }

    private function topCustomers(array $filters)
    {
        return $this->baseSalesOrderQuery($filters)
            ->leftJoin('customers as c', 'so.customer_id', '=', 'c.id')
            ->select(
                'so.customer_id',
                DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
                DB::raw('COUNT(so.id) as total_orders'),
                DB::raw('SUM(so.total_amount) as total_sales')
            )
            ->groupBy('so.customer_id', 'c.name')
            ->orderByDesc('total_sales')
            ->limit($filters['limit'])
            ->get();
    }

    private function topProducts(array $filters)
    {
        return $this->baseSalesOrderQuery($filters)
            ->join('sales_order_items as soi', 'so.id', '=', 'soi.sales_order_id')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
            ->select(
                'p.id as product_id',
                DB::raw("CONCAT(lb.name, ' ', p.pack_size, ' ', lu.name) as product_name"),
                DB::raw('SUM(soi.quantity) as total_quantity'),
                DB::raw('SUM((soi.price - COALESCE(soi.discount_per_unit, 0)) * soi.quantity) as total_sales')
            )
            ->groupBy('p.id', 'lb.name', 'p.pack_size', 'lu.name')
            ->orderByDesc('total_quantity')
            ->limit($filters['limit'])
            ->get();
    }

    private function dailySalesOrders(array $filters)
    {
        return $this->baseSalesOrderQuery($filters, false)
            ->leftJoin('customers as c', 'so.customer_id', '=', 'c.id')
            ->leftJoin('sales_order_items as soi', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('products as p', 'soi.product_id', '=', 'p.id')
            ->leftJoin('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->leftJoin('list_units as lu', 'p.unit_id', '=', 'lu.id')
            ->whereDate('so.order_date', $filters['day'])
            ->select(
                'so.id',
                'so.so_number',
                'so.order_date',
                DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
                'so.payment_mode',
                'so.total_amount',
                DB::raw("COALESCE(
                    GROUP_CONCAT(
                        CONCAT(lb.name, ' ', p.pack_size, ' ', lu.name, ' x', soi.quantity)
                        ORDER BY soi.id ASC
                        SEPARATOR ', '
                    ),
                    '-'
                ) as sold_products")
            )
            ->groupBy(
                'so.id',
                'so.so_number',
                'so.order_date',
                'c.name',
                'so.payment_mode',
                'so.total_amount'
            )
            ->orderByDesc('so.order_date')
            ->orderByDesc('so.id')
            ->limit($filters['limit'])
            ->get();
    }

    private function productSalesReport(array $filters)
    {
        return $this->baseSalesOrderQuery($filters)
            ->join('sales_order_items as soi', 'so.id', '=', 'soi.sales_order_id')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
            ->select(
                'p.id as product_id',
                DB::raw("CONCAT(lb.name, ' ', p.pack_size, ' ', lu.name) as product_name"),
                DB::raw('COUNT(DISTINCT so.id) as total_orders'),
                DB::raw('SUM(soi.quantity) as total_quantity'),
                DB::raw('SUM((soi.price - COALESCE(soi.discount_per_unit, 0)) * soi.quantity) as total_sales')
            )
            ->groupBy('p.id', 'lb.name', 'p.pack_size', 'lu.name')
            ->orderByDesc('total_sales')
            ->limit($filters['limit'])
            ->get();
    }

    private function customerSalesReport(array $filters)
    {
        return $this->baseSalesOrderQuery($filters)
            ->leftJoin('customers as c', 'so.customer_id', '=', 'c.id')
            ->select(
                'so.customer_id',
                DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
                DB::raw('COUNT(so.id) as total_orders'),
                DB::raw('SUM(so.total_amount) as total_sales'),
                DB::raw('AVG(so.total_amount) as average_order_value')
            )
            ->groupBy('so.customer_id', 'c.name')
            ->orderByDesc('total_sales')
            ->limit($filters['limit'])
            ->get();
    }

    private function salesRepReport(array $filters)
    {
        return $this->baseSalesOrderQuery($filters)
            ->leftJoin('employees as e', 'so.sales_rep_id', '=', 'e.id')
            ->select(
                'so.sales_rep_id',
                DB::raw("COALESCE(CONCAT(e.firstname, ' ', e.lastname), 'Unassigned') as sales_rep_name"),
                DB::raw('COUNT(so.id) as total_orders'),
                DB::raw('SUM(so.total_amount) as total_sales'),
                DB::raw('AVG(so.total_amount) as average_order_value')
            )
            ->groupBy('so.sales_rep_id', 'e.firstname', 'e.lastname')
            ->orderByDesc('total_sales')
            ->limit($filters['limit'])
            ->get();
    }

    private function paymentSummary(array $filters)
    {
        $rows = $this->baseSalesOrderQuery($filters, false)
            ->select(
                DB::raw($this->normalizedPaymentModeSql() . ' as payment_type'),
                DB::raw('COUNT(so.id) as total_orders'),
                DB::raw('SUM(so.total_amount) as total_sales')
            )
            ->groupBy('payment_type')
            ->orderByDesc('total_sales')
            ->get()
            ->keyBy('payment_type');

        return [
            'cash' => $rows->get('cash'),
            'credit' => $rows->get('credit'),
            'other' => $rows->get('other'),
            'grand_total_sales' => $rows->sum(fn ($row) => (float) $row->total_sales),
            'grand_total_orders' => $rows->sum(fn ($row) => (int) $row->total_orders),
        ];
    }

    private function baseSalesOrderQuery(array $filters, bool $applyDateRange = true): Builder
    {
        $query = DB::table('sales_orders as so')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->where('ls.slug', '!=', 'cancelled');

        if ($applyDateRange) {
            $query->whereBetween('so.order_date', [$filters['from'], $filters['to']]);
        }

        if (!empty($filters['location_id'])) {
            $query->where('so.location_id', $filters['location_id']);
        }

        $this->applyPaymentModeFilter($query, $filters['payment_mode']);

        return $query;
    }

    private function applyPaymentModeFilter(Builder $query, string $paymentMode): void
    {
        if ($paymentMode === 'all') {
            return;
        }

        if ($paymentMode === 'cash') {
            $query->whereRaw($this->normalizedPaymentModeSql() . " = 'cash'");
            return;
        }

        if ($paymentMode === 'credit') {
            $query->whereRaw($this->normalizedPaymentModeSql() . " = 'credit'");
        }
    }

    private function normalizedPaymentModeSql(): string
    {
        return "CASE
            WHEN LOWER(COALESCE(so.payment_mode, 'cash')) IN ('cash', 'cash sales') THEN 'cash'
            WHEN LOWER(COALESCE(so.payment_mode, 'cash')) IN ('credit', 'credit sales') THEN 'credit'
            ELSE 'other'
        END";
    }
}
