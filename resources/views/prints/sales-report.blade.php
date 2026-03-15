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
    @endphp

    <div class="header">
        <h1>Sales Report</h1>
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
            </tr>
            <tr>
                <td>{{ $filters['from'] ?? '-' }}</td>
                <td>{{ $filters['to'] ?? '-' }}</td>
                <td>{{ $filters['day'] ?? '-' }}</td>
                <td>{{ strtoupper($filters['payment_mode'] ?? 'all') }}</td>
                <td>{{ (int) ($filters['limit'] ?? 10) }}</td>
            </tr>
        </table>
    </div>

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
</body>
</html>
