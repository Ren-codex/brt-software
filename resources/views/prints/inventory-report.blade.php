<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Position</title>
    <style>
        @page { size: A4 landscape; margin: 20px 22px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111827; }
        .header { border-bottom: 2px solid #1a3a32; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 15px; color: #1a3a32; }
        .header p  { margin: 3px 0 0; color: #4b5563; font-size: 9px; }
        .totals { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .totals td { padding: 5px 8px; border: 1px solid #dcebe5; font-size: 9px; }
        .totals td.label { background: #f4faf8; color: #4a6b62; text-transform: uppercase;
                           letter-spacing: .04em; font-size: 8px; }
        .totals td.value { font-weight: bold; font-size: 10.5px; color: #1a3a32; }
        table.rows { width: 100%; border-collapse: collapse; }
        thead th { background: #edf5f2; color: #335c52; font-size: 8.5px; text-transform: uppercase;
                   padding: 5px 6px; border-bottom: 1px solid #b8d9cc; text-align: left; }
        thead th.num { text-align: right; }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #f0f5f3; font-size: 9px; }
        tbody td.num { text-align: right; }
        .code { color: #94a3b8; font-size: 8.5px; }
        .chip { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
        .in_stock     { background: #dcfce7; color: #166534; }
        .low_stock    { background: #fef3c7; color: #92400e; }
        .out_of_stock { background: #fee2e2; color: #7c2d12; }
        .expiring     { background: #ffedd5; color: #9a3412; }
        .expired      { background: #fee2e2; color: #7c2d12; }
        .grand-total { background: #e4f0eb; }
        .grand-total td { font-weight: bold; border-top: 2px solid #3d8d7a; padding: 6px; }
        h2 { font-size: 11px; color: #1a3a32; margin: 16px 0 6px; }
        .footer { margin-top: 14px; font-size: 8.5px; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>
@php
    $totals = $report['totals'];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $statusLabels = [
        'in_stock' => 'In stock', 'low_stock' => 'Low stock', 'out_of_stock' => 'Out of stock',
        'expiring' => 'Expiring', 'expired' => 'Expired',
    ];
@endphp

<div class="header">
    <h1>Inventory Position</h1>
    <p>Stock held as at {{ $report['generated_at'] }}. A snapshot, not a period — figures are what is on hand now.</p>
</div>

<table class="totals">
    <tr>
        <td class="label">Stock value (at cost)</td>
        <td class="label">Retail value (potential)</td>
        <td class="label">Products</td>
        <td class="label">Batches</td>
        <td class="label">Units on hand</td>
        <td class="label">Low stock</td>
        <td class="label">Out of stock</td>
        <td class="label">Expiring</td>
    </tr>
    <tr>
        <td class="value">{{ $peso($totals['stock_value']) }}</td>
        <td class="value">{{ $peso($totals['retail_value']) }}</td>
        <td class="value">{{ number_format($totals['products']) }}</td>
        <td class="value">{{ number_format($totals['batches']) }}</td>
        <td class="value">{{ number_format($totals['quantity']) }}</td>
        <td class="value">{{ number_format($totals['low_stock']) }}</td>
        <td class="value">{{ number_format($totals['out_of_stock']) }}</td>
        <td class="value">{{ number_format($totals['expiring']) }}</td>
    </tr>
</table>

<table class="rows">
    <thead>
        <tr>
            <th>Batch</th>
            <th>Product</th>
            <th>Brand</th>
            <th>Source</th>
            <th class="num">Qty</th>
            <th class="num">Min</th>
            <th class="num">Unit cost</th>
            <th class="num">Retail</th>
            <th class="num">Stock value</th>
            <th class="num">Retail value</th>
            <th>Expires</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($report['rows'] as $row)
            <tr>
                <td class="code">{{ $row['batch_code'] }}</td>
                <td>{{ $row['product_name'] }}</td>
                <td>{{ $row['brand'] }}</td>
                <td>{{ $row['is_converted'] ? 'Converted' : 'Received' }}</td>
                <td class="num">{{ number_format($row['quantity']) }}</td>
                <td class="num">{{ $row['minimum_stock'] ?: '—' }}</td>
                <td class="num">{{ $peso($row['unit_cost']) }}</td>
                <td class="num">{{ $peso($row['retail_price']) }}</td>
                <td class="num">{{ $peso($row['stock_value']) }}</td>
                <td class="num">{{ $peso($row['retail_value']) }}</td>
                <td>{{ $row['expiration_date'] ?? '—' }}</td>
                <td><span class="chip {{ $row['status'] }}">{{ $statusLabels[$row['status']] ?? $row['status'] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="12" style="text-align:center; color:#9ca3af; padding:14px;">No stock matches these filters.</td></tr>
        @endforelse
        @if (count($report['rows']))
            <tr class="grand-total">
                <td colspan="4">Total</td>
                <td class="num">{{ number_format($totals['quantity']) }}</td>
                <td colspan="3"></td>
                <td class="num">{{ $peso($totals['stock_value']) }}</td>
                <td class="num">{{ $peso($totals['retail_value']) }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
    </tbody>
</table>

@if (count($report['by_brand']))
    <h2>By brand</h2>
    <table class="rows">
        <thead>
            <tr>
                <th>Brand</th>
                <th class="num">Batches</th>
                <th class="num">Quantity</th>
                <th class="num">Stock value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['by_brand'] as $brand)
                <tr>
                    <td>{{ $brand['brand'] }}</td>
                    <td class="num">{{ number_format($brand['batches']) }}</td>
                    <td class="num">{{ number_format($brand['quantity']) }}</td>
                    <td class="num">{{ $peso($brand['stock_value']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">BRT Accounting System — generated {{ $report['generated_at'] }}</div>
</body>
</html>
