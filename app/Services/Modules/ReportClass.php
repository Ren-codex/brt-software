<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use App\Services\System\Permission\PermissionService;

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
            'receipt_report' => $this->receiptReport($filters),
            'discount_summary' => $this->discountSummary($filters),
            'tax_summary' => $this->taxSummary(),
            'employee_summary' => $this->employeeSummary($filters),
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
                DB::raw("CONCAT(lb.name, ' ', p.weight, ' ', lu.name) as product_name"),
                DB::raw('SUM(soi.quantity) as total_quantity'),
                DB::raw('SUM((soi.price - COALESCE(soi.discount_per_unit, 0)) * soi.quantity) as total_sales')
            )
            ->groupBy('p.id', 'lb.name', 'p.weight', 'lu.name')
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
                        CONCAT(lb.name, ' ', p.weight, ' ', lu.name, ' x', soi.quantity)
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
                DB::raw("CONCAT(lb.name, ' ', p.weight, ' ', lu.name) as product_name"),
                DB::raw('COUNT(DISTINCT so.id) as total_orders'),
                DB::raw('SUM(soi.quantity) as total_quantity'),
                DB::raw('SUM((soi.price - COALESCE(soi.discount_per_unit, 0)) * soi.quantity) as total_sales')
            )
            ->groupBy('p.id', 'lb.name', 'p.weight', 'lu.name')
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

    private function employeeSummary(array $filters)
    {
        $soSub = DB::table('sales_orders as so')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->where('ls.slug', '!=', 'cancelled')
            ->whereBetween('so.order_date', [$filters['from'], $filters['to']])
            ->when(!empty($filters['location_id']), function ($query) use ($filters) {
                $query->where('so.location_id', $filters['location_id']);
            })
            ->whereNotNull('so.sales_rep_id')
            ->select(
                'so.sales_rep_id',
                DB::raw('COUNT(so.id) as so_count'),
                DB::raw('SUM(so.total_amount) as so_total')
            )
            ->groupBy('so.sales_rep_id');

        $arSub = DB::table('ar_invoices as ai')
            ->join('sales_orders as so', 'ai.sales_order_id', '=', 'so.id')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->where('ls.slug', '!=', 'cancelled')
            ->whereBetween('ai.invoice_date', [$filters['from'], $filters['to']])
            ->when(!empty($filters['location_id']), function ($query) use ($filters) {
                $query->where('so.location_id', $filters['location_id']);
            })
            ->whereNotNull('so.sales_rep_id')
            ->select(
                'so.sales_rep_id',
                DB::raw('COUNT(ai.id) as ar_count'),
                DB::raw('SUM(ai.amount_due) as ar_total'),
                DB::raw('SUM(ai.balance_due) as ar_balance_due')
            )
            ->groupBy('so.sales_rep_id');

        $receiptSub = DB::table('receipts as r')
            ->join('ar_invoices as ai', 'r.ar_invoice_id', '=', 'ai.id')
            ->join('sales_orders as so', 'ai.sales_order_id', '=', 'so.id')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->where('ls.slug', '!=', 'cancelled')
            ->whereBetween('r.receipt_date', [$filters['from'], $filters['to']])
            ->when(!empty($filters['location_id']), function ($query) use ($filters) {
                $query->where('so.location_id', $filters['location_id']);
            })
            ->whereNotNull('so.sales_rep_id')
            ->select(
                'so.sales_rep_id',
                DB::raw('COUNT(r.id) as receipt_count'),
                DB::raw('SUM(r.amount_paid) as receipt_total')
            )
            ->groupBy('so.sales_rep_id');

        $query = DB::table('employees as e')
            ->leftJoinSub($soSub, 'sod', 'sod.sales_rep_id', '=', 'e.id')
            ->leftJoinSub($arSub, 'ard', 'ard.sales_rep_id', '=', 'e.id')
            ->leftJoinSub($receiptSub, 'rd', 'rd.sales_rep_id', '=', 'e.id')
            ->select(
                'e.id as employee_id',
                DB::raw("CONCAT(e.firstname, ' ', e.lastname) as employee_name"),
                DB::raw('COALESCE(sod.so_count, 0) as so_count'),
                DB::raw('COALESCE(sod.so_total, 0) as so_total'),
                DB::raw('COALESCE(ard.ar_count, 0) as ar_count'),
                DB::raw('COALESCE(ard.ar_total, 0) as ar_total'),
                DB::raw('COALESCE(ard.ar_balance_due, 0) as ar_balance_due'),
                DB::raw('COALESCE(rd.receipt_count, 0) as receipt_count'),
                DB::raw('COALESCE(rd.receipt_total, 0) as receipt_total')
            )
            ->whereRaw('COALESCE(sod.so_count, 0) + COALESCE(ard.ar_count, 0) + COALESCE(rd.receipt_count, 0) > 0');

        $this->applyEmployeeSummaryScope($query);

        return $query
            ->orderByDesc(DB::raw('COALESCE(sod.so_total, 0) + COALESCE(ard.ar_total, 0) + COALESCE(rd.receipt_total, 0)'))
            ->get();
    }

    private function applyEmployeeSummaryScope(Builder $query): void
    {
        $user = Auth::user();
        if (!$user || app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin')) {
            return;
        }

        $employeeId = $user->employee?->id;

        if (!$employeeId) {
            return;
        }

        $query->where('e.id', $employeeId);
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

    private function receiptReport(array $filters)
    {
        $query = DB::table('receipts as r')
            ->join('ar_invoices as ai', 'r.ar_invoice_id', '=', 'ai.id')
            ->join('sales_orders as so', 'ai.sales_order_id', '=', 'so.id')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->leftJoin('customers as c', 'r.customer_id', '=', 'c.id')
            ->when(!empty($filters['from']) && !empty($filters['to']), function ($query) use ($filters) {
                $query->whereBetween('so.order_date', [$filters['from'], $filters['to']]);
            })
            ->when(!empty($filters['location_id']), function ($query) use ($filters) {
                $query->where('so.location_id', $filters['location_id']);
            })
            ->where('ls.slug', '!=', 'cancelled');

        $this->applyPaymentModeFilter($query, $filters['payment_mode']);

        return $query
            ->select(
                'r.id',
                'r.receipt_number',
                'r.receipt_date',
                'r.amount_paid',
                'r.balance_due',
                'so.so_number',
                DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
                'so.payment_mode'
            )
            ->orderByDesc('r.receipt_date')
            ->orderByDesc('r.id')
            ->limit($filters['limit'])
            ->get();
    }

    private function discountSummary(array $filters): array
    {
        $baseQuery = $this->baseSalesOrderQuery($filters);

        $totals = (clone $baseQuery)
            ->selectRaw('COUNT(CASE WHEN COALESCE(so.total_discount, 0) > 0 THEN 1 END) as discounted_orders')
            ->selectRaw('COALESCE(SUM(so.total_discount), 0) as total_discount')
            ->selectRaw('COALESCE(AVG(CASE WHEN COALESCE(so.total_discount, 0) > 0 THEN so.total_discount END), 0) as average_discount')
            ->first();

        $orders = $baseQuery
            ->leftJoin('customers as c', 'so.customer_id', '=', 'c.id')
            ->select(
                'so.id',
                'so.so_number',
                'so.order_date',
                DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
                'so.total_amount',
                'so.total_discount'
            )
            ->where('so.total_discount', '>', 0)
            ->orderByDesc('so.total_discount')
            ->orderByDesc('so.order_date')
            ->limit($filters['limit'])
            ->get();

        return [
            'discounted_orders' => (int) data_get($totals, 'discounted_orders', 0),
            'total_discount' => (float) data_get($totals, 'total_discount', 0),
            'average_discount' => (float) data_get($totals, 'average_discount', 0),
            'orders' => $orders,
        ];
    }

    private function taxSummary(): array
    {
        return [
            'enabled' => false,
            'total_tax' => 0,
            'message' => 'Tax reporting is not yet configured in sales orders.',
        ];
    }

    private function baseSalesOrderQuery(array $filters, bool $applyDateRange = true): Builder
    {
        $query = DB::table('sales_orders as so')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->where('ls.slug', '!=', 'cancelled');

        $this->applySalesRepScope($query);

        if ($applyDateRange) {
            $query->whereBetween('so.order_date', [$filters['from'], $filters['to']]);
        }

        if (!empty($filters['location_id'])) {
            $query->where('so.location_id', $filters['location_id']);
        }

        $this->applyPaymentModeFilter($query, $filters['payment_mode']);

        return $query;
    }

    private function applySalesRepScope(Builder $query): void
    {
        $user = Auth::user();
        if (!$user || app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin')) {
            return;
        }

        $employeeId = $user->employee?->id;

        if (!$employeeId) {
            return;
        }

        $query->where(function ($salesOrderQuery) use ($employeeId) {
            $salesOrderQuery
                ->where('so.added_by_id', $employeeId)
                ->orWhere('so.sales_rep_id', $employeeId);
        });
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
