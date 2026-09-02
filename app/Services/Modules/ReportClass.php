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
        $orders = $this->baseSalesOrderQuery($filters, false)
            ->leftJoin('customers as c', 'so.customer_id', '=', 'c.id')
            ->whereDate('so.order_date', $filters['day'])
            ->select(
                'so.id',
                'so.so_number',
                'so.order_date',
                DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
                'so.payment_mode',
                'so.total_amount'
            )
            ->orderByDesc('so.order_date')
            ->orderByDesc('so.id')
            ->limit($filters['limit'])
            ->get();

        if ($orders->isEmpty()) {
            return $orders;
        }

        // The sold-products summary is composed in PHP rather than with
        // GROUP_CONCAT. MySQL spells it `GROUP_CONCAT(x SEPARATOR ', ')` and
        // SQLite spells it `GROUP_CONCAT(x, ', ')` with different semantics, so
        // the SQL version made this whole report impossible to cover in tests.
        // The row count here is already bounded by the report's limit.
        $soldProducts = DB::table('sales_order_items as soi')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
            ->whereIn('soi.sales_order_id', $orders->pluck('id')->all())
            ->orderBy('soi.id')
            ->select(
                'soi.sales_order_id',
                'soi.quantity',
                DB::raw("CONCAT(lb.name, ' ', p.weight, ' ', lu.name) as product_name")
            )
            ->get()
            ->groupBy('sales_order_id')
            ->map(fn ($items) => $items
                ->map(fn ($item) => $item->product_name.' x'.$item->quantity)
                ->implode(', '));

        foreach ($orders as $order) {
            $order->sold_products = $soldProducts[$order->id] ?? '-';
        }

        return $orders;
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

        $qtySub = DB::table('sales_orders as so')
            ->join('sales_order_items as soi', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->where('ls.slug', '!=', 'cancelled')
            ->whereBetween('so.order_date', [$filters['from'], $filters['to']])
            ->when(!empty($filters['location_id']), function ($query) use ($filters) {
                $query->where('so.location_id', $filters['location_id']);
            })
            ->whereNotNull('so.sales_rep_id')
            ->select(
                'so.sales_rep_id',
                DB::raw('ROUND(SUM(COALESCE(p.weight, 0) * soi.quantity) / 25, 2) as sold_quantity')
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
            ->leftJoinSub($qtySub, 'qd', 'qd.sales_rep_id', '=', 'e.id')
            ->leftJoinSub($arSub, 'ard', 'ard.sales_rep_id', '=', 'e.id')
            ->leftJoinSub($receiptSub, 'rd', 'rd.sales_rep_id', '=', 'e.id')
            ->select(
                'e.id as employee_id',
                DB::raw("CONCAT(e.firstname, ' ', e.lastname) as employee_name"),
                DB::raw('COALESCE(sod.so_count, 0) as so_count'),
                DB::raw('COALESCE(qd.sold_quantity, 0) as sold_quantity'),
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

        // This query is built by hand rather than from baseSalesOrderQuery(), so
        // the rep scope has to be applied explicitly — without it a Sales Rep
        // sees every receipt in the business, including other reps'. It joins
        // sales_orders as `so`, which is the alias the scope expects.
        $this->applySalesRepScope($query);

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

    /**
     * The detail behind a single report row.
     *
     * Aggregate types reuse baseSalesOrderQuery(), so the report's date range,
     * location and payment-mode filters carry over — and, critically, so does
     * applySalesRepScope(). A Sales Rep therefore cannot drill into another
     * rep's orders, and an unauthorised id simply yields an empty result
     * rather than leaking that the record exists.
     */
    public function drilldown(array $filters, string $type, ?int $id): array
    {
        return match ($type) {
            'customer', 'product', 'sales_rep' => $this->drilldownOrders($filters, $type, $id),
            'order'   => $this->drilldownOrder($filters, (int) $id),
            'receipt' => $this->drilldownReceipt($filters, (int) $id),
            default   => $this->emptyDrilldown(),
        };
    }

    private function emptyDrilldown(string $label = 'Not found'): array
    {
        return [
            'mode'    => 'orders',
            'context' => ['label' => $label, 'meta' => []],
            'totals'  => ['orders' => 0, 'quantity' => null, 'sales' => 0.0],
            'rows'    => [],
        ];
    }

    /**
     * Orders behind an aggregate row. `customer` and `sales_rep` accept a null
     * id, which is how the report represents "Walk-in Customer" / "Unassigned".
     */
    private function drilldownOrders(array $filters, string $type, ?int $id): array
    {
        $isProduct = $type === 'product';

        $query = $this->baseSalesOrderQuery($filters)
            ->leftJoin('customers as c', 'so.customer_id', '=', 'c.id');

        if ($isProduct) {
            $query->join('sales_order_items as soi', 'so.id', '=', 'soi.sales_order_id')
                ->where('soi.product_id', $id);
        } elseif ($type === 'customer') {
            $id ? $query->where('so.customer_id', $id) : $query->whereNull('so.customer_id');
        } else {
            $id ? $query->where('so.sales_rep_id', $id) : $query->whereNull('so.sales_rep_id');
        }

        $select = [
            'so.id',
            'so.so_number',
            'so.order_date',
            'so.payment_mode',
            DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
        ];

        if ($isProduct) {
            $select[] = DB::raw('SUM(soi.quantity) as quantity');
            $select[] = DB::raw('SUM((soi.price - COALESCE(soi.discount_per_unit, 0)) * soi.quantity) as amount');
            $query->groupBy('so.id', 'so.so_number', 'so.order_date', 'so.payment_mode', 'c.name');
        } else {
            $select[] = DB::raw('NULL as quantity');
            $select[] = 'so.total_amount as amount';
        }

        $rows = $query->select($select)
            ->orderByDesc('so.order_date')
            ->orderByDesc('so.id')
            ->limit(200)
            ->get();

        return [
            'mode'    => 'orders',
            'context' => ['label' => $this->drilldownLabel($type, $id), 'meta' => []],
            'totals'  => [
                'orders'   => $rows->count(),
                'quantity' => $isProduct ? (float) $rows->sum('quantity') : null,
                'sales'    => (float) $rows->sum('amount'),
            ],
            'rows'    => $rows,
        ];
    }

    /** Resolve the display name for an aggregate row, server-side. */
    private function drilldownLabel(string $type, ?int $id): string
    {
        if ($type === 'product') {
            $product = DB::table('products as p')
                ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
                ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
                ->where('p.id', $id)
                ->selectRaw("CONCAT(lb.name, ' ', p.weight, ' ', lu.name) as name")
                ->value('name');

            return $product ?: 'Unknown product';
        }

        if ($type === 'customer') {
            return $id
                ? (DB::table('customers')->where('id', $id)->value('name') ?: 'Unknown customer')
                : 'Walk-in Customer';
        }

        if (!$id) {
            return 'Unassigned';
        }

        $rep = DB::table('employees')->where('id', $id)
            ->selectRaw("CONCAT(firstname, ' ', lastname) as name")
            ->value('name');

        return $rep ?: 'Unknown sales rep';
    }

    /** A single sales order with its line items. */
    private function drilldownOrder(array $filters, int $id): array
    {
        // Date range is deliberately skipped: the Daily Sales Orders table is
        // filtered by `day`, not the from/to range, so re-applying the range
        // could hide a row the user just clicked. Rep scope still applies.
        $order = $this->baseSalesOrderQuery($filters, false)
            ->leftJoin('customers as c', 'so.customer_id', '=', 'c.id')
            ->leftJoin('employees as e', 'so.sales_rep_id', '=', 'e.id')
            ->leftJoin('list_locations as ll', 'so.location_id', '=', 'll.id')
            ->where('so.id', $id)
            ->select(
                'so.id',
                'so.so_number',
                'so.order_date',
                'so.payment_mode',
                'so.total_amount',
                'ls.name as status_name',
                'll.name as location_name',
                DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name"),
                DB::raw("COALESCE(CONCAT(e.firstname, ' ', e.lastname), 'Unassigned') as sales_rep_name")
            )
            ->first();

        if (!$order) {
            return $this->emptyDrilldown('Order not available');
        }

        $rows = $this->orderLineItems($id);

        return [
            'mode'    => 'record',
            'context' => [
                'label' => $order->so_number,
                'meta'  => [
                    ['label' => 'Customer',     'value' => $order->customer_name],
                    ['label' => 'Order date',   'value' => $order->order_date],
                    ['label' => 'Payment mode', 'value' => $order->payment_mode ?: '—'],
                    ['label' => 'Status',       'value' => $order->status_name ?: '—'],
                    ['label' => 'Sales rep',    'value' => $order->sales_rep_name],
                    ['label' => 'Location',     'value' => $order->location_name ?: '—'],
                ],
            ],
            'totals'  => [
                'orders'   => null,
                'quantity' => (float) $rows->sum('quantity'),
                'sales'    => (float) $order->total_amount,
            ],
            'rows'    => $rows,
        ];
    }

    /** A single receipt, shown with the line items of the order it paid. */
    private function drilldownReceipt(array $filters, int $id): array
    {
        $receipt = DB::table('receipts as r')
            ->join('ar_invoices as ai', 'r.ar_invoice_id', '=', 'ai.id')
            ->join('sales_orders as so', 'ai.sales_order_id', '=', 'so.id')
            ->join('list_statuses as ls', 'so.status_id', '=', 'ls.id')
            ->leftJoin('customers as c', 'r.customer_id', '=', 'c.id')
            ->where('r.id', $id)
            ->where('ls.slug', '!=', 'cancelled');

        // receiptReport() builds its own query and never applies rep scope, so
        // apply it here explicitly rather than inheriting that gap.
        $this->applySalesRepScope($receipt);

        $receipt = $receipt->select(
            'r.id',
            'r.receipt_number',
            'r.receipt_date',
            'r.amount_paid',
            'r.balance_due',
            'r.payment_mode',
            'so.id as sales_order_id',
            'so.so_number',
            DB::raw("COALESCE(c.name, 'Walk-in Customer') as customer_name")
        )->first();

        if (!$receipt) {
            return $this->emptyDrilldown('Receipt not available');
        }

        $rows = $this->orderLineItems((int) $receipt->sales_order_id);

        return [
            'mode'    => 'record',
            'context' => [
                'label' => $receipt->receipt_number,
                'meta'  => [
                    ['label' => 'Customer',     'value' => $receipt->customer_name],
                    ['label' => 'Receipt date', 'value' => $receipt->receipt_date],
                    ['label' => 'Against order', 'value' => $receipt->so_number],
                    ['label' => 'Payment mode', 'value' => $receipt->payment_mode ?: '—'],
                    ['label' => 'Amount paid',  'value' => number_format((float) $receipt->amount_paid, 2)],
                    ['label' => 'Balance due',  'value' => number_format((float) $receipt->balance_due, 2)],
                ],
            ],
            'totals'  => [
                'orders'   => null,
                'quantity' => (float) $rows->sum('quantity'),
                'sales'    => (float) $receipt->amount_paid,
            ],
            'rows'    => $rows,
        ];
    }

    private function orderLineItems(int $salesOrderId)
    {
        return DB::table('sales_order_items as soi')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
            ->where('soi.sales_order_id', $salesOrderId)
            ->select(
                'soi.id',
                DB::raw("CONCAT(lb.name, ' ', p.weight, ' ', lu.name) as product_name"),
                'soi.batch_code',
                'soi.quantity',
                'soi.price',
                'soi.discount_per_unit',
                DB::raw('(soi.price - COALESCE(soi.discount_per_unit, 0)) * soi.quantity as amount')
            )
            ->orderBy('soi.id')
            ->get();
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

        // These two columns hold different identities: sales_orders.added_by_id
        // is a foreign key onto users, while sales_rep_id is an employee id.
        // Comparing both against the employee id (as this previously did) let a
        // rep see unrelated orders whenever some user id happened to equal their
        // employee id, while hiding orders they had actually created.
        $userId = $user->id;

        $query->where(function ($salesOrderQuery) use ($userId, $employeeId) {
            $salesOrderQuery
                ->where('so.added_by_id', $userId)
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
