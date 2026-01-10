<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    /**
     * Get revenue reports.
     *
     * @param array $filters Optional filters (future extension)
     * @return \Illuminate\Support\Collection
     */
    public function getReports(array $filters = [])
    {
        $query = DB::table('sales_order_items as soi')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
            ->join('received_stocks as rso', 'soi.batch_code', '=', 'rso.batch_code')
            ->join('received_items as ri', function ($join) {
                $join->on('rso.id', '=', 'ri.received_id')
                     ->on('soi.product_id', '=', 'ri.product_id');
            })
            ->join('inventory_stocks as inv', 'ri.id', '=', 'inv.received_item_id')
            // Only include sales that have a paid receipt: sales_order -> ar_invoice -> receipt(status=paid)
            ->join('ar_invoices as ai', 'soi.sales_order_id', '=', 'ai.sales_order_id')
            ->join('receipts as r', 'ai.id', '=', 'r.ar_invoice_id')
            ->join('remittances as rem', 'r.remittance_id', '=', 'rem.id')
            ->join('list_statuses as rs', 'r.status_id', '=', 'rs.id')
            ->where('rs.slug', '=', 'paid')
            ->whereNotNull('r.remittance_id')
            ->select(
                'rem.remittance_date',
                DB::raw("CONCAT(lb.name, ' ', p.pack_size, ' ', lu.name) as product_name"),
                DB::raw('SUM(soi.quantity * soi.price) as revenue'),
                DB::raw('SUM(soi.quantity * ri.unit_cost) as total_unit_cost'),
                DB::raw('SUM(soi.quantity * soi.price) - SUM(soi.quantity * ri.unit_cost) as profit')
            )
            ->groupBy('rem.remittance_date', 'soi.product_id', 'lb.name', 'p.pack_size', 'lu.name');

        // Apply filters (placeholder for future extensions, e.g. date ranges)
        // if (!empty($filters['from'])) { ... }

        return $query->get();
    }
}
