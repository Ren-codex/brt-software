<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class InventoryReportExport implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(private array $report)
    {
    }

    public function array(): array
    {
        $totals = $this->report['totals'];

        $rows = [
            ['Inventory Position'],
            ['Generated', $this->report['generated_at']],
            [],
            ['Stock value (at cost)', $totals['stock_value']],
            ['Retail value (potential)', $totals['retail_value']],
            ['Products', $totals['products']],
            ['Batches', $totals['batches']],
            ['Units on hand', $totals['quantity']],
            ['Low stock', $totals['low_stock']],
            ['Out of stock', $totals['out_of_stock']],
            ['Expiring or expired', $totals['expiring']],
            [],
            ['Batch', 'Product', 'Brand', 'Source', 'Quantity', 'Minimum', 'Unit cost', 'Retail', 'Wholesale', 'Stock value', 'Retail value', 'Expires', 'Status'],
        ];

        foreach ($this->report['rows'] as $row) {
            $rows[] = [
                $row['batch_code'],
                $row['product_name'],
                $row['brand'],
                $row['is_converted'] ? 'Converted' : 'Received',
                $row['quantity'],
                $row['minimum_stock'],
                $row['unit_cost'],
                $row['retail_price'],
                $row['wholesale_price'],
                $row['stock_value'],
                $row['retail_value'],
                $row['expiration_date'] ?? '',
                $this->label($row['status']),
            ];
        }

        $rows[] = [];
        $rows[] = ['By brand'];
        $rows[] = ['Brand', 'Batches', 'Quantity', 'Stock value'];
        foreach ($this->report['by_brand'] as $brand) {
            $rows[] = [$brand['brand'], $brand['batches'], $brand['quantity'], $brand['stock_value']];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Inventory Position';
    }

    private function label(string $status): string
    {
        return [
            'in_stock'     => 'In stock',
            'low_stock'    => 'Low stock',
            'out_of_stock' => 'Out of stock',
            'expiring'     => 'Expiring soon',
            'expired'      => 'Expired',
        ][$status] ?? $status;
    }
}
