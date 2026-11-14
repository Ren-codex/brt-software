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
        $externalLocationIds = \App\Models\ListLocation::where('name', '!=', 'Main Warehouse')->pluck('id');
        $internalLocationIds = \App\Models\ListLocation::where('name', 'Main Warehouse')->pluck('id');

        $query = DB::table('sales_order_items as soi')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->join('list_brands as lb', 'p.brand_id', '=', 'lb.id')
            ->join('list_units as lu', 'p.unit_id', '=', 'lu.id')
            // The batch_code lives on inventory_stocks, not received_stocks, so a
            // sold line is traced to its batch there and then back to the
            // received line that priced it. (The old join read rso.batch_code,
            // a column received_stocks does not have, so this report always
            // errored and showed nothing.)
            ->join('inventory_stocks as inv', 'soi.batch_code', '=', 'inv.batch_code')
            ->join('received_items as ri', function ($join) {
                $join->on('inv.received_item_id', '=', 'ri.id')
                     ->on('soi.product_id', '=', 'ri.product_id');
            })
            // Only include sales that have a paid receipt: sales_order -> ar_invoice -> receipt(status=paid)
            ->join('ar_invoices as ai', 'soi.sales_order_id', '=', 'ai.sales_order_id')
            ->join('receipts as r', 'ai.id', '=', 'r.ar_invoice_id')
            ->join('remittances as rem', 'r.remittance_id', '=', 'rem.id')
            ->join('list_statuses as rs', 'r.status_id', '=', 'rs.id')
            ->join('sales_orders as so', 'soi.sales_order_id', '=', 'so.id')
            ->where('rs.slug', '=', 'paid')
            ->whereNotNull('r.remittance_id')
            ->where(function ($q) use ($filters, $externalLocationIds, $internalLocationIds) {
                if (isset($filters['is_external']) && $filters['is_external']) {
                    $q->whereIn('so.location_id', $externalLocationIds);
                } else {
                    $q->where(function ($subQ) use ($internalLocationIds) {
                        $subQ->whereIn('so.location_id', $internalLocationIds)
                             ->orWhereNull('so.location_id');
                    });
                }
            })
            ->select(
                'rem.remittance_date',
                DB::raw("CONCAT(lb.name, ' ', p.weight, ' ', lu.name) as product_name"),
                DB::raw('SUM(soi.quantity * soi.price) as revenue'),
                // Unit cost is unchanged; the per-unit tracking expense is added
                // on top only when working out profit (landed cost = unit cost +
                // tracking expense). COALESCE keeps older rows (null expense)
                // exactly as they were.
                DB::raw('SUM(soi.quantity * ri.unit_cost) as total_unit_cost'),
                DB::raw('SUM(soi.quantity * COALESCE(ri.tracking_expense, 0)) as total_tracking_expense'),
                DB::raw('SUM(soi.quantity * soi.price) - SUM(soi.quantity * (ri.unit_cost + COALESCE(ri.tracking_expense, 0))) as profit')
            )
            ->groupBy('rem.remittance_date', 'soi.product_id', 'lb.name', 'p.weight', 'lu.name');

        // Apply filters (placeholder for future extensions, e.g. date ranges)
        // if (!empty($filters['from'])) { ... }

        return $query->get();
    }
}
