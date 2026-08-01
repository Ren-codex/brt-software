<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
        }

        h1, h2 {
            margin: 0;
        }

        .header {
            margin-bottom: 14px;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 8px;
        }

        .subtext {
            margin-top: 4px;
            color: #4b5563;
            font-size: 10px;
        }

        .section {
            margin-top: 14px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .grid {
            width: 100%;
        }

        .grid td {
            border: 0;
            padding: 0 6px 0 0;
            width: 50%;
            vertical-align: top;
        }
    </style>
</head>
<body>
    @php
        $summary = $reportData['payment_summary'] ?? [];
        $topCustomers = $reportData['top_customers'] ?? [];
        $topProducts = $reportData['top_products'] ?? [];
        $productSalesReport = $reportData['product_sales_report'] ?? [];
        $customerSalesReport = $reportData['customer_sales_report'] ?? [];
        $salesRepReport = $reportData['sales_rep_report'] ?? [];
        $dailySales = $reportData['daily_sales_orders'] ?? [];
        $receiptReport = $reportData['receipt_report'] ?? [];
        $discountSummary = $reportData['discount_summary'] ?? [];
        $discountOrders = $discountSummary['orders'] ?? [];
        $taxSummary = $reportData['tax_summary'] ?? [];
        $reportType = $filters['report_type'] ?? 'sales-summary';
        $reportTitle = match ($reportType) {
            'sales-by-item' => 'Sales by Item',
            'sales-by-employee' => 'Sales by Employee',
            'sales-by-payment-type' => 'Sales by Payment Type',
            'receipt' => 'Receipt',
            'discount' => 'Discount',
            'taxes' => 'Taxes',
            default => 'Sales Summary',
        };
    @endphp

    <div class="header">
        <h1>{{ $reportTitle }} Report</h1>
        <div class="subtext">
            Generated on {{ now()->format('F d, Y h:i A') }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Filters</div>
        <table>
            <tr>
                <th>From</th>
                <th>To</th>
                <th>Day</th>
                <th>Payment Mode</th>
                <th>Top Limit</th>
                <th>Report Type</th>
            </tr>
            <tr>
                <td>{{ $filters['from'] ?? '-' }}</td>
                <td>{{ $filters['to'] ?? '-' }}</td>
                <td>{{ $filters['day'] ?? '-' }}</td>
                <td>{{ strtoupper($filters['payment_mode'] ?? 'all') }}</td>
                <td>{{ (int) ($filters['limit'] ?? 10) }}</td>
                <td>{{ $reportTitle }}</td>
            </tr>
        </table>
    </div>

    @if ($reportType === 'sales-by-item')
        <div class="section">
            <table class="grid">
                <tr>
                    <td>
                        <div class="section-title">Top Products</div>
                        <table>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Sold</th>
                                <th class="text-right">Sales</th>
                            </tr>
                            @forelse ($topProducts as $item)
                                <tr>
                                    <td>{{ data_get($item, 'product_name', '-') }}</td>
                                    <td class="text-right">{{ (float) data_get($item, 'total_quantity', 0) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">No product data found.</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                    <td>
                        <div class="section-title">Sales by Item</div>
                        <table>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-right">Sales</th>
                            </tr>
                            @forelse ($productSalesReport as $item)
                                <tr>
                                    <td>{{ data_get($item, 'product_name', '-') }}</td>
                                    <td class="text-right">{{ (int) data_get($item, 'total_orders', 0) }}</td>
                                    <td class="text-right">{{ (float) data_get($item, 'total_quantity', 0) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No product sales data found.</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    @elseif ($reportType === 'sales-by-employee')
        <div class="section">
            <div class="section-title">Sales by Employee</div>
            <table>
                <tr>
                    <th>Sales Rep</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Average Order</th>
                    <th class="text-right">Sales</th>
                </tr>
                @forelse ($salesRepReport as $item)
                    <tr>
                        <td>{{ data_get($item, 'sales_rep_name', 'Unassigned') }}</td>
                        <td class="text-right">{{ (int) data_get($item, 'total_orders', 0) }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'average_order_value', 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No sales rep data found.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    @elseif ($reportType === 'sales-by-payment-type')
        <div class="section">
            <div class="section-title">Payment Summary</div>
            <table>
                <tr>
                    <th>Type</th>
                    <th class="text-right">Total Orders</th>
                    <th class="text-right">Total Sales</th>
                </tr>
                @foreach (['cash', 'credit', 'other'] as $type)
                    <tr>
                        <td>{{ strtoupper($type) }}</td>
                        <td class="text-right">{{ (int) data_get($summary, $type . '.total_orders', 0) }}</td>
                        <td class="text-right">{{ number_format((float) data_get($summary, $type . '.total_sales', 0), 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th>GRAND TOTAL</th>
                    <th class="text-right">{{ (int) data_get($summary, 'grand_total_orders', 0) }}</th>
                    <th class="text-right">{{ number_format((float) data_get($summary, 'grand_total_sales', 0), 2) }}</th>
                </tr>
            </table>
        </div>
    @elseif ($reportType === 'receipt')
        <div class="section">
            <div class="section-title">Receipt Report</div>
            <table>
                <tr>
                    <th>Receipt #</th>
                    <th>Date</th>
                    <th>SO #</th>
                    <th>Customer</th>
                    <th>Payment</th>
                    <th class="text-right">Amount Paid</th>
                    <th class="text-right">Balance Due</th>
                </tr>
                @forelse ($receiptReport as $item)
                    <tr>
                        <td>{{ data_get($item, 'receipt_number', '-') }}</td>
                        <td>{{ data_get($item, 'receipt_date', '-') }}</td>
                        <td>{{ data_get($item, 'so_number', '-') }}</td>
                        <td>{{ data_get($item, 'customer_name', 'Walk-in Customer') }}</td>
                        <td>{{ data_get($item, 'payment_mode', '-') }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'amount_paid', 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'balance_due', 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No receipts found for this filter.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    @elseif ($reportType === 'discount')
        <div class="section">
            <div class="section-title">Discount Summary</div>
            <table>
                <tr>
                    <th>Discounted Orders</th>
                    <th>Total Discount</th>
                    <th>Average Discount</th>
                </tr>
                <tr>
                    <td>{{ (int) data_get($discountSummary, 'discounted_orders', 0) }}</td>
                    <td>{{ number_format((float) data_get($discountSummary, 'total_discount', 0), 2) }}</td>
                    <td>{{ number_format((float) data_get($discountSummary, 'average_discount', 0), 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Discounted Sales Orders</div>
            <table>
                <tr>
                    <th>SO #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="text-right">Discount</th>
                    <th class="text-right">Net Sales</th>
                </tr>
                @forelse ($discountOrders as $item)
                    <tr>
                        <td>{{ data_get($item, 'so_number', '-') }}</td>
                        <td>{{ data_get($item, 'order_date', '-') }}</td>
                        <td>{{ data_get($item, 'customer_name', 'Walk-in Customer') }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'total_discount', 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'total_amount', 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No discounted sales found.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    @elseif ($reportType === 'taxes')
        <div class="section">
            <div class="section-title">Taxes</div>
            <table>
                <tr>
                    <th>Total Tax</th>
                    <th>Message</th>
                </tr>
                <tr>
                    <td>{{ number_format((float) data_get($taxSummary, 'total_tax', 0), 2) }}</td>
                    <td>{{ data_get($taxSummary, 'message', 'Tax reporting is not configured yet.') }}</td>
                </tr>
            </table>
        </div>
    @else
        <div class="section">
            <div class="section-title">Payment Summary</div>
            <table>
                <tr>
                    <th>Type</th>
                    <th class="text-right">Total Orders</th>
                    <th class="text-right">Total Sales</th>
                </tr>
                @foreach (['cash', 'credit', 'other'] as $type)
                    <tr>
                        <td>{{ strtoupper($type) }}</td>
                        <td class="text-right">{{ (int) data_get($summary, $type . '.total_orders', 0) }}</td>
                        <td class="text-right">{{ number_format((float) data_get($summary, $type . '.total_sales', 0), 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th>GRAND TOTAL</th>
                    <th class="text-right">{{ (int) data_get($summary, 'grand_total_orders', 0) }}</th>
                    <th class="text-right">{{ number_format((float) data_get($summary, 'grand_total_sales', 0), 2) }}</th>
                </tr>
            </table>
        </div>

        <div class="section">
            <table class="grid">
                <tr>
                    <td>
                        <div class="section-title">Top Customers</div>
                        <table>
                            <tr>
                                <th>Customer</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Sales</th>
                            </tr>
                            @forelse ($topCustomers as $item)
                                <tr>
                                    <td>{{ data_get($item, 'customer_name', 'Walk-in Customer') }}</td>
                                    <td class="text-right">{{ (int) data_get($item, 'total_orders', 0) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">No customer data found.</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                    <td>
                        <div class="section-title">Top Products</div>
                        <table>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Sold</th>
                                <th class="text-right">Sales</th>
                            </tr>
                            @forelse ($topProducts as $item)
                                <tr>
                                    <td>{{ data_get($item, 'product_name', '-') }}</td>
                                    <td class="text-right">{{ (float) data_get($item, 'total_quantity', 0) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">No product data found.</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Product Sales Report</div>
            <table>
                <tr>
                    <th>Product</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Sales</th>
                </tr>
                @forelse ($productSalesReport as $item)
                    <tr>
                        <td>{{ data_get($item, 'product_name', '-') }}</td>
                        <td class="text-right">{{ (int) data_get($item, 'total_orders', 0) }}</td>
                        <td class="text-right">{{ (float) data_get($item, 'total_quantity', 0) }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No product sales data found.</td>
                    </tr>
                @endforelse
            </table>
        </div>

        <div class="section">
            <table class="grid">
                <tr>
                    <td>
                        <div class="section-title">Customer Sales Report</div>
                        <table>
                            <tr>
                                <th>Customer</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Average Order</th>
                                <th class="text-right">Sales</th>
                            </tr>
                            @forelse ($customerSalesReport as $item)
                                <tr>
                                    <td>{{ data_get($item, 'customer_name', 'Walk-in Customer') }}</td>
                                    <td class="text-right">{{ (int) data_get($item, 'total_orders', 0) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'average_order_value', 0), 2) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No customer sales data found.</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                    <td>
                        <div class="section-title">Sales Rep Report</div>
                        <table>
                            <tr>
                                <th>Sales Rep</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Average Order</th>
                                <th class="text-right">Sales</th>
                            </tr>
                            @forelse ($salesRepReport as $item)
                                <tr>
                                    <td>{{ data_get($item, 'sales_rep_name', 'Unassigned') }}</td>
                                    <td class="text-right">{{ (int) data_get($item, 'total_orders', 0) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'average_order_value', 0), 2) }}</td>
                                    <td class="text-right">{{ number_format((float) data_get($item, 'total_sales', 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No sales rep data found.</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Daily Sales Orders</div>
            <table>
                <tr>
                    <th>SO #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Sold Products</th>
                    <th>Payment</th>
                    <th class="text-right">Amount</th>
                </tr>
                @forelse ($dailySales as $item)
                    <tr>
                        <td>{{ data_get($item, 'so_number', '-') }}</td>
                        <td>{{ data_get($item, 'order_date', '-') }}</td>
                        <td>{{ data_get($item, 'customer_name', 'Walk-in Customer') }}</td>
                        <td>{{ data_get($item, 'sold_products', '-') }}</td>
                        <td>{{ data_get($item, 'payment_mode', '-') }}</td>
                        <td class="text-right">{{ number_format((float) data_get($item, 'total_amount', 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No sales orders found for selected day.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    @endif
</body>
</html>
