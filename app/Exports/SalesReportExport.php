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
        $reportType = $this->filters['report_type'] ?? 'sales-summary';

        $filtersRows = [
            ['Sales Report Export'],
            ['From', $this->filters['from'] ?? ''],
            ['To', $this->filters['to'] ?? ''],
            ['Day', $this->filters['day'] ?? ''],
            ['Payment Mode', strtoupper((string) ($this->filters['payment_mode'] ?? 'all'))],
            ['Top Limit', (int) ($this->filters['limit'] ?? 10)],
            ['Report Type', $this->reportTitle($reportType)],
        ];

        return array_merge(
            [new SalesReportSheet('Filters', $filtersRows)],
            $this->buildReportSheets($reportType, $summary)
        );
    }

    private function buildReportSheets(string $reportType, array $summary): array
    {
        return match ($reportType) {
            'sales-by-item' => [
                new SalesReportSheet('Top Products', $this->topProductsRows()),
                new SalesReportSheet('Sales By Item', $this->productSalesRows()),
            ],
            'sales-by-employee' => [
                new SalesReportSheet('Sales By Employee', $this->salesRepRows()),
            ],
            'sales-by-payment-type' => [
                new SalesReportSheet('Payment Summary', $this->paymentSummaryRows($summary)),
            ],
            'receipt' => [
                new SalesReportSheet('Receipt Report', $this->receiptRows()),
            ],
            'discount' => [
                new SalesReportSheet('Discount Summary', $this->discountSummaryRows()),
                new SalesReportSheet('Discount Orders', $this->discountOrdersRows()),
            ],
            'taxes' => [
                new SalesReportSheet('Taxes', $this->taxRows()),
            ],
            default => [
                new SalesReportSheet('Payment Summary', $this->paymentSummaryRows($summary)),
                new SalesReportSheet('Top Customers', $this->topCustomersRows()),
                new SalesReportSheet('Top Products', $this->topProductsRows()),
                new SalesReportSheet('Product Sales', $this->productSalesRows()),
                new SalesReportSheet('Customer Sales', $this->customerSalesRows()),
                new SalesReportSheet('Sales Rep Sales', $this->salesRepRows()),
                new SalesReportSheet('Daily Sales Orders', $this->dailySalesRows()),
            ],
        };
    }

    private function reportTitle(string $reportType): string
    {
        return match ($reportType) {
            'sales-by-item' => 'Sales by Item',
            'sales-by-employee' => 'Sales by Employee',
            'sales-by-payment-type' => 'Sales by Payment Type',
            'receipt' => 'Receipt',
            'discount' => 'Discount',
            'taxes' => 'Taxes',
            default => 'Sales Summary',
        };
    }

    private function paymentSummaryRows(array $summary): array
    {
        $rows = [
            ['Type', 'Total Orders', 'Total Sales'],
        ];

        foreach (['cash', 'credit', 'other'] as $type) {
            $item = data_get($summary, $type);
            $rows[] = [
                strtoupper($type),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        $rows[] = [
            'GRAND TOTAL',
            (int) data_get($summary, 'grand_total_orders', 0),
            (float) data_get($summary, 'grand_total_sales', 0),
        ];

        return $rows;
    }

    private function topCustomersRows(): array
    {
        $rows = [
            ['Customer', 'Total Orders', 'Total Sales'],
        ];

        foreach (($this->reportData['top_customers'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        return $rows;
    }

    private function topProductsRows(): array
    {
        $rows = [
            ['Product', 'Total Sold', 'Total Sales'],
        ];

        foreach (($this->reportData['top_products'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'product_name', ''),
                (float) data_get($item, 'total_quantity', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        return $rows;
    }

    private function productSalesRows(): array
    {
        $rows = [
            ['Product', 'Total Orders', 'Total Quantity', 'Total Sales'],
        ];

        foreach (($this->reportData['product_sales_report'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'product_name', ''),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'total_quantity', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        return $rows;
    }

    private function customerSalesRows(): array
    {
        $rows = [
            ['Customer', 'Total Orders', 'Average Order', 'Total Sales'],
        ];

        foreach (($this->reportData['customer_sales_report'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'average_order_value', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        return $rows;
    }

    private function salesRepRows(): array
    {
        $rows = [
            ['Sales Rep', 'Total Orders', 'Average Order', 'Total Sales'],
        ];

        foreach (($this->reportData['sales_rep_report'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'sales_rep_name', 'Unassigned'),
                (int) data_get($item, 'total_orders', 0),
                (float) data_get($item, 'average_order_value', 0),
                (float) data_get($item, 'total_sales', 0),
            ];
        }

        return $rows;
    }

    private function dailySalesRows(): array
    {
        $rows = [
            ['SO #', 'Date', 'Customer', 'Sold Products', 'Payment', 'Amount'],
        ];

        foreach (($this->reportData['daily_sales_orders'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'so_number', ''),
                (string) data_get($item, 'order_date', ''),
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (string) data_get($item, 'sold_products', '-'),
                (string) data_get($item, 'payment_mode', ''),
                (float) data_get($item, 'total_amount', 0),
            ];
        }

        return $rows;
    }

    private function receiptRows(): array
    {
        $rows = [
            ['Receipt #', 'Date', 'SO #', 'Customer', 'Payment', 'Amount Paid', 'Balance Due'],
        ];

        foreach (($this->reportData['receipt_report'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'receipt_number', '-'),
                (string) data_get($item, 'receipt_date', ''),
                (string) data_get($item, 'so_number', '-'),
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (string) data_get($item, 'payment_mode', ''),
                (float) data_get($item, 'amount_paid', 0),
                (float) data_get($item, 'balance_due', 0),
            ];
        }

        return $rows;
    }

    private function discountSummaryRows(): array
    {
        $summary = $this->reportData['discount_summary'] ?? [];

        return [
            ['Discounted Orders', (int) data_get($summary, 'discounted_orders', 0)],
            ['Total Discount', (float) data_get($summary, 'total_discount', 0)],
            ['Average Discount', (float) data_get($summary, 'average_discount', 0)],
        ];
    }

    private function discountOrdersRows(): array
    {
        $rows = [
            ['SO #', 'Date', 'Customer', 'Discount', 'Net Sales'],
        ];

        foreach (($this->reportData['discount_summary']['orders'] ?? []) as $item) {
            $rows[] = [
                (string) data_get($item, 'so_number', ''),
                (string) data_get($item, 'order_date', ''),
                (string) data_get($item, 'customer_name', 'Walk-in Customer'),
                (float) data_get($item, 'total_discount', 0),
                (float) data_get($item, 'total_amount', 0),
            ];
        }

        return $rows;
    }

    private function taxRows(): array
    {
        $summary = $this->reportData['tax_summary'] ?? [];

        return [
            ['Total Tax', (float) data_get($summary, 'total_tax', 0)],
            ['Message', (string) data_get($summary, 'message', 'Tax reporting is not configured yet.')],
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
