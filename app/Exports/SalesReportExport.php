<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class SalesReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $filters,
        private array $reportData
    ) {
    }

    public function sheets(): array
    {
        $summary = $this->reportData['payment_summary'] ?? [];

        $filtersRows = [
            ['Sales Report Export'],
            ['From', $this->filters['from'] ?? ''],
            ['To', $this->filters['to'] ?? ''],
            ['Day', $this->filters['day'] ?? ''],
            ['Payment Mode', strtoupper((string) ($this->filters['payment_mode'] ?? 'all'))],
            ['Top Limit', (int) ($this->filters['limit'] ?? 10)],
        ];

        $paymentSummaryRows = [
            ['Type', 'Total Orders', 'Total Sales'],
        ];
        foreach (['cash', 'credit', 'other'] as $type) {
            $item = data_get($summary, $type);
            $paymentSummaryRows[] = [
                strtoupper($type),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        $paymentSummaryRows[] = [
            'GRAND TOTAL',
            (int) data_get($summary, 'grand_total_orders', 0),
            (float) data_get($summary, 'grand_total_sales', 0),
        ];

        $topCustomersRows = [
            ['Customer', 'Total Orders', 'Total Sales'],
        ];
        foreach (($this->reportData['top_customers'] ?? []) as $item) {
            $topCustomersRows[] = [
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        $topProductsRows = [
            ['Product', 'Total Sold', 'Total Sales'],
        ];
        foreach (($this->reportData['top_products'] ?? []) as $item) {
            $topProductsRows[] = [
                (string) data_get($item, 'product_name', ''),
                (float) data_get($item, 'total_quantity', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        $productSalesRows = [
            ['Product', 'Total Orders', 'Total Quantity', 'Total Sales'],
        ];
        foreach (($this->reportData['product_sales_report'] ?? []) as $item) {
            $productSalesRows[] = [
                (string) data_get($item, 'product_name', ''),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'total_quantity', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        $customerSalesRows = [
            ['Customer', 'Total Orders', 'Average Order', 'Total Sales'],
        ];
        foreach (($this->reportData['customer_sales_report'] ?? []) as $item) {
            $customerSalesRows[] = [
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'average_order_value', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        $salesRepRows = [
            ['Sales Rep', 'Total Orders', 'Average Order', 'Total Sales'],
        ];
        foreach (($this->reportData['sales_rep_report'] ?? []) as $item) {
            $salesRepRows[] = [
                (string) data_get($item, 'sales_rep_name', 'Unassigned'),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'average_order_value', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        $dailySalesRows = [
            ['SO #', 'Date', 'Customer', 'Sold Products', 'Payment', 'Amount'],
        ];
        foreach (($this->reportData['daily_sales_orders'] ?? []) as $item) {
            $dailySalesRows[] = [
                (string) data_get($item, 'so_number', ''),
                (string) data_get($item, 'order_date', ''),
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (string) data_get($item, 'sold_products', '-'),
                (string) data_get($item, 'payment_mode', ''),
                (float) data_get($item, 'total_amount', 0),
            ];
        }

        return [
            new SalesReportSheet('Filters', $filtersRows),
            new SalesReportSheet('Payment Summary', $paymentSummaryRows),
            new SalesReportSheet('Top Customers', $topCustomersRows),
            new SalesReportSheet('Top Products', $topProductsRows),
            new SalesReportSheet('Product Sales', $productSalesRows),
            new SalesReportSheet('Customer Sales', $customerSalesRows),
            new SalesReportSheet('Sales Rep Sales', $salesRepRows),
            new SalesReportSheet('Daily Sales Orders', $dailySalesRows),
        ];
    }
}

class SalesReportSheet implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(
        private string $title,
        private array $rows
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
